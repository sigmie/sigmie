<?php

declare(strict_types=1);

namespace Sigmie\Semantic;

use Carbon\Carbon;
use DateTime as PHPDateTime;
use Exception;
use InvalidArgumentException;
use Sigmie\Document\Document;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\Combo;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\Image;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Shared\Collection;
use Sigmie\Shared\UsesApis;
use Sigmie\Support\VectorMath;

class DocumentProcessor
{
    use UsesApis;

    public const DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        protected Properties $properties
    ) {}

    public function populateComboFields(Document $document): Document
    {
        $comboFields = $this->getComboFields();

        $comboFields->each(function (Combo $field) use ($document): void {
            $value = $this->buildComboValue($field, $document);

            if ($value !== []) {
                $combinedValue = implode(' ', $value);
                $document->_source[$field->name()] = $combinedValue;
            }
        });

        return $document;
    }

    public function populateEmbeddings(Document $document): Document
    {
        [$result] = $this->populateEmbeddingsBatch([$document]);

        return $result;
    }

    /**
     * Populate embeddings for many documents in a single pass, batching
     * provider calls across documents grouped by (api, dims).
     *
     * @param  array<int, Document>  $documents
     * @return array<int, Document>
     */
    public function populateEmbeddingsBatch(array $documents): array
    {
        if (! $this->hasApi() || $documents === []) {
            return $documents;
        }

        $semanticFields = $this->properties->nestedSemanticFields()->toArray();

        if ($semanticFields === []) {
            return $documents;
        }

        $workItems = [];
        $existingByDoc = [];

        foreach ($documents as $docIdx => $document) {
            foreach ($semanticFields as $field) {
                $reused = $this->reuseExistingEmbeddings($field, $document);

                if ($reused !== null) {
                    $existingByDoc[$docIdx][$field->fullPath()] = $reused;

                    continue;
                }

                $value = $this->extractFieldValue($field, $document);

                if ($value === []) {
                    continue;
                }

                foreach ($this->planFieldWorkItems($field, $value, $docIdx) as $item) {
                    $workItems[] = $item;
                }
            }
        }

        $workItems = $this->runBatches($workItems);

        return $this->assembleEmbeddings($documents, $workItems, $existingByDoc);
    }

    protected function reuseExistingEmbeddings(Text|Image $field, Document $document): ?array
    {
        $existingEmbeddings = dot($document->_source)->get('_embeddings.'.$field->fullPath());

        if (! $existingEmbeddings || ! is_array($existingEmbeddings)) {
            return null;
        }

        foreach ($field->vectorFields() as $vectorField) {
            $name = $vectorField->name;

            if (! isset($existingEmbeddings[$name]) || empty($existingEmbeddings[$name])) {
                return null;
            }
        }

        return $existingEmbeddings;
    }

    protected function planFieldWorkItems(Text|Image $field, array $value, int $docIdx): array
    {
        $items = [];
        $isImage = $field instanceof Image;

        foreach ($field->vectorFields() as $vectorField) {
            $vector = $vectorField instanceof Nested ? $vectorField->properties['vector'] : $vectorField;
            $strategy = $vector->strategy();
            $dims = (string) $vector->dims();
            $normalize = $vector instanceof BaseVector ? $vector->autoNormalizeVector() : true;
            $apiName = $vectorField->apiName;

            $prepared = $strategy->prepare($value);

            foreach ($prepared as $pos => $text) {
                $items[] = [
                    'doc_idx' => $docIdx,
                    'field_path' => $field->fullPath(),
                    'field' => $field,
                    'vector_name' => $vectorField->name,
                    'pos' => $pos,
                    'text' => $text,
                    'dims' => $dims,
                    'strategy' => $strategy,
                    'normalize' => $normalize,
                    'api_name' => $apiName,
                    'is_image' => $isImage,
                    'vector' => [],
                ];
            }
        }

        return $items;
    }

    protected function runBatches(array $workItems): array
    {
        if ($workItems === []) {
            return $workItems;
        }

        $groups = [];

        foreach ($workItems as $idx => $item) {
            $key = $item['api_name'].'|'.$item['dims'].'|'.($item['is_image'] ? 'image' : 'text');
            $groups[$key][] = $idx;
        }

        foreach ($groups as $indices) {
            $api = $this->getApi($workItems[$indices[0]]['api_name']);

            $api ?? throw new Exception(sprintf("Embeddings API '%s' is not registered", $workItems[$indices[0]]['api_name']));

            $chunkSize = max(1, min(self::DEFAULT_BATCH_SIZE, $api->maxBatchSize()));

            foreach (array_chunk($indices, $chunkSize) as $chunk) {
                $payload = array_map(fn (int $i): array => [
                    'name' => $workItems[$i]['vector_name'],
                    'text' => $workItems[$i]['text'],
                    'dims' => $workItems[$i]['dims'],
                    'vector' => [],
                ], $chunk);

                $result = $api->batchEmbed($payload);

                foreach ($chunk as $pos => $i) {
                    $workItems[$i]['vector'] = $result[$pos]['vector'] ?? [];
                }
            }
        }

        return $workItems;
    }

    protected function assembleEmbeddings(array $documents, array $workItems, array $existingByDoc): array
    {
        $perDoc = [];

        foreach ($workItems as $item) {
            $perDoc[$item['doc_idx']][$item['field_path']]['_field'] = $item['field'];
            $perDoc[$item['doc_idx']][$item['field_path']][$item['vector_name']]['strategy'] = $item['strategy'];
            $perDoc[$item['doc_idx']][$item['field_path']][$item['vector_name']]['normalize'] = $item['normalize'];
            $perDoc[$item['doc_idx']][$item['field_path']][$item['vector_name']]['vectors'][$item['pos']] = $item['vector'];
        }

        foreach ($documents as $docIdx => $document) {
            $flatEmbeddings = $existingByDoc[$docIdx] ?? [];

            $fieldsForDoc = $perDoc[$docIdx] ?? [];

            foreach ($fieldsForDoc as $path => $vectorsByName) {
                $field = $vectorsByName['_field'];
                unset($vectorsByName['_field']);

                $formatted = [];

                foreach ($vectorsByName as $vectorName => $data) {
                    ksort($data['vectors']);
                    $vectors = array_values($data['vectors']);
                    $formatted[$vectorName] = $data['strategy']->format($vectors, $data['normalize']);
                }

                $formatted = $this->applyBoostScaling($field, $formatted, $document);

                $flatEmbeddings[$path] = $formatted;
            }

            $document['_embeddings'] = $this->buildNestedStructure($flatEmbeddings);

            $documents[$docIdx] = $document;
        }

        return $documents;
    }

    public function formatDateTimeFields(Document $document): Document
    {
        $fieldNames = $this->properties->fieldNames(withParent: false);

        foreach ($fieldNames as $fieldPath) {
            $value = dot($document->_source)->get($fieldPath);

            if ($value === null) {
                continue;
            }

            $field = $this->properties->get($fieldPath);

            if (! $field) {
                continue;
            }

            $formattedValue = $this->formatDateTimeValue($value, $field);

            if ($formattedValue !== $value) {
                $dotHelper = dot($document->_source);
                $dotHelper->set($fieldPath, $formattedValue);
                $document->_source = $dotHelper->all();
            }
        }

        return $document;
    }

    public function validateFields(Document $document): Document
    {
        $errors = [];

        $fieldNames = $this->properties->fieldNames(withParent: false);

        foreach ($fieldNames as $fieldPath) {
            $value = dot($document->_source)->get($fieldPath);

            if ($value === null) {
                continue;
            }

            $field = $this->properties->get($fieldPath);

            if (! $field) {
                continue;
            }

            $this->validateFieldValue($fieldPath, $value, $field, $errors);
        }

        if (! empty($errors)) {
            throw new InvalidArgumentException(
                'Document validation failed: '.implode(', ', $errors)
            );
        }

        return $document;
    }

    protected function getComboFields(): Collection
    {
        return $this->properties
            ->textFields()
            ->filter(fn (Text $field): bool => $field instanceof Combo);
    }

    protected function buildComboValue(Combo $field, Document $document): array
    {
        return (new Collection($field->sourceFields()))
            ->map(fn ($sourceField) => $document->get($sourceField))
            ->filter(fn ($value): bool => $value !== null)
            ->flatMap(fn ($value): array => is_array($value) ? $value : [$value])
            ->toArray();
    }

    protected function extractFieldValue(Text|Image $field, Document $document): array
    {
        $nestedAncestor = $this->findNestedAncestor($field);

        if ($nestedAncestor) {
            return $this->extractNestedValue($field, $document, $nestedAncestor);
        }

        return $this->extractSimpleValue($field, $document);
    }

    protected function findNestedAncestor(Text|Image $field): ?string
    {
        if (! str_contains($field->fullPath(), '.')) {
            return null;
        }

        $parts = explode('.', $field->fullPath());
        // Check each ancestor path to see if it's a nested field
        $counter = count($parts);

        // Check each ancestor path to see if it's a nested field
        for ($i = 1; $i < $counter; $i++) {
            $ancestorPath = implode('.', array_slice($parts, 0, $i));
            $ancestorField = $this->properties->get($ancestorPath);

            if ($ancestorField instanceof Nested) {
                return $ancestorPath;
            }
        }

        return null;
    }

    protected function isNestedField(Text|Image $field): bool
    {
        return $field->nestedPath() !== null;
    }

    protected function extractNestedValue(Text|Image $field, Document $document, string $nestedPath): array
    {
        $parentArray = dot($document->_source)->get($nestedPath);

        if (! $parentArray || ! is_array($parentArray)) {
            return [];
        }

        // Get the relative path from the nested field to this field
        $relativePath = substr($field->fullPath(), strlen($nestedPath) + 1);

        // Check if this is a single object (associative array) or array of objects (indexed array)
        // Single object: ['key' => 'value'], Array of objects: [['key' => 'value'], ['key' => 'value']]
        $isIndexedArray = array_keys($parentArray) === range(0, count($parentArray) - 1);

        if (! $isIndexedArray) {
            // Single object - extract value directly
            $value = dot($parentArray)->get($relativePath);

            return $value !== null ? (is_array($value) ? $value : [$value]) : [];
        }

        // Array of objects - map over each item
        return (new Collection($parentArray))
            ->map(fn ($item) => dot($item)->get($relativePath))
            ->filter(fn ($value): bool => $value !== null)
            ->toArray();
    }

    protected function parseNestedPath(string $fullPath): array
    {
        $parts = explode('.', $fullPath);
        $nestedFieldName = array_pop($parts);
        $parentPath = implode('.', $parts);

        return [$parentPath, $nestedFieldName];
    }

    protected function extractSimpleValue(Text|Image $field, Document $document): array
    {
        $value = dot($document->_source)->get($field->fullPath());

        if (! $value) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    protected function buildNestedStructure(array $flatEmbeddings): array
    {
        $result = [];

        foreach ($flatEmbeddings as $path => $vectors) {
            $dotHelper = dot($result);
            $dotHelper->set($path, $vectors);
            $result = $dotHelper->all();
        }

        return $result;
    }

    protected function formatDateTimeValue(mixed $value, Type $field): mixed
    {
        if ($value instanceof PHPDateTime || $value instanceof Carbon) {
            if ($field instanceof Date) {
                return $value->format('Y-m-d');
            }

            if ($field instanceof DateTime) {
                return $value->format('Y-m-d\TH:i:s.uP');
            }
        }

        if (is_array($value)) {
            return array_map(fn ($item): mixed => $this->formatDateTimeValue($item, $field), $value);
        }

        return $value;
    }

    protected function validateFieldValue(string $fieldPath, mixed $value, Type $field, array &$errors): void
    {
        // For Range and GeoPoint fields the array IS the value — a Range is ['gt' => 10, 'lt' => 20]
        // and a GeoPoint is ['lat' => .., 'lon' => ..] (or a list of those). Validate it whole;
        // iterating would recurse into the scalar lat/lon and reject them.
        if ($field instanceof Range || $field instanceof GeoPoint) {
            [$isValid, $errorMessage] = $field->validate($fieldPath, $value);

            if (! $isValid) {
                $errors[] = $errorMessage;
            }

            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->validateFieldValue($fieldPath, $item, $field, $errors);
            }

            return;
        }

        [$isValid, $errorMessage] = $field->validate($fieldPath, $value);

        if (! $isValid) {
            $errors[] = $errorMessage;
        }
    }

    protected function applyBoostScaling(Text|Image $field, array $formattedVectors, Document $document): array
    {
        foreach ($field->vectorFields() as $vectorField) {
            $vector = $vectorField instanceof Nested ? $vectorField->properties['vector'] : $vectorField;

            // Only SigmieVector has boost support
            if (! ($vector instanceof BaseVector)) {
                continue;
            }

            $boostedByField = $vector->boostedByField();

            if ($boostedByField === null) {
                continue;
            }

            // Validate boost field
            $this->validateBoostField($field->fullPath(), $boostedByField);

            // Extract boost value from document
            $boostValue = dot($document->_source)->get($boostedByField);

            if ($boostValue === null) {
                throw new Exception(sprintf("Boost field '%s' is not present in document for semantic field '%s'", $boostedByField, $field->fullPath()));
            }

            if (! is_numeric($boostValue) || $boostValue <= 0) {
                throw new Exception(sprintf("Boost field '%s' must be a positive number. Got: %s", $boostedByField, $boostValue));
            }

            // Get the vector name
            $name = $vectorField->name;

            // Scale the vector
            $currentVector = $formattedVectors[$name] ?? null;
            if ($currentVector === null) {
                continue;
            }

            if (empty($currentVector)) {
                continue;
            }

            // Handle nested vector structure (ScriptScore strategy)
            if (isset($currentVector[0]['vector'])) {
                // ScriptScore format: array of objects with 'vector' field
                foreach ($currentVector as $index => $item) {
                    $scaled = VectorMath::scale($item['vector'], (float) $boostValue);

                    // Apply normalization if needed
                    if ($vector->autoNormalizeVector()) {
                        $scaled = VectorMath::normalize($scaled);
                    }

                    $formattedVectors[$name][$index]['vector'] = $scaled;
                }
            } else {
                // Regular format: flat array of numbers
                $scaled = VectorMath::scale($currentVector, (float) $boostValue);

                // Apply normalization if needed
                if ($vector->autoNormalizeVector()) {
                    $scaled = VectorMath::normalize($scaled);
                }

                $formattedVectors[$name] = $scaled;
            }
        }

        return $formattedVectors;
    }

    protected function validateBoostField(string $fieldPath, string $boostField): void
    {
        $field = $this->properties->get($boostField);

        if ($field === null) {
            throw new Exception(sprintf("Boost field '%s' referenced by semantic field '%s' does not exist in properties", $boostField, $fieldPath));
        }

        if (! ($field instanceof Number)) {
            throw new Exception(sprintf("Boost field '%s' must be a Number type (float/double/integer). Got: ", $boostField).$field->typeName());
        }
    }
}
