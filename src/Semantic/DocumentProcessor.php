<?php

declare(strict_types=1);

namespace Sigmie\Semantic;

use Sigmie\Mappings\Types\Range;
use Carbon\Carbon;
use DateTime as PHPDateTime;
use Exception;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Document\Document;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Combo;
use Sigmie\Mappings\Types\Image;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Shared\Collection;
use Sigmie\Shared\UsesApis;
use Sigmie\Support\VectorMath;

class DocumentProcessor
{
    use UsesApis;

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
        // Check if any embeddings API is registered
        if (!$this->hasApi()) {
            // No embeddings API registered, skip embeddings
            return $document;
        }

        $embeddings = $this->properties
            ->nestedSemanticFields()
            ->mapWithKeys(fn(Text|Image $field) => [
                $field->fullPath => $this->processField($field, $document)
            ])
            ->filter(fn($vectors): bool => !empty($vectors))
            ->toArray();

        $document['_embeddings'] = $this->buildNestedStructure($embeddings);

        return $document;
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

            if (!$field) {
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

            if (!$field) {
                continue;
            }

            $this->validateFieldValue($fieldPath, $value, $field, $errors);
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(
                'Document validation failed: ' . implode(', ', $errors)
            );
        }

        return $document;
    }

    protected function getComboFields(): Collection
    {
        return $this->properties
            ->textFields()
            ->filter(fn(Text $field): bool => $field instanceof Combo);
    }

    protected function buildComboValue(Combo $field, Document $document): array
    {
        return (new Collection($field->sourceFields()))
            ->map(fn($sourceField) => $document->get($sourceField))
            ->filter(fn($value): bool => $value !== null)
            ->flatMap(fn($value): array => is_array($value) ? $value : [$value])
            ->toArray();
    }

    protected function processField(Text|Image $field, Document $document): array
    {
        // Check if embeddings already exist for this field
        $existingEmbeddings = dot($document->_source)->get('_embeddings.' . $field->fullPath);

        if ($existingEmbeddings && is_array($existingEmbeddings)) {
            // Check if all required vector fields already have embeddings
            $vectorFields = $field->vectorFields();
            $allExist = true;
            foreach ($vectorFields as $vectorField) {
                $name = $vectorField instanceof Nested ? $vectorField->name : $vectorField->name;
                if (!isset($existingEmbeddings[$name]) || empty($existingEmbeddings[$name])) {
                    $allExist = false;
                    break;
                }
            }

            if ($allExist) {
                // Return existing embeddings, no need to regenerate
                return $existingEmbeddings;
            }
        }

        $value = $this->extractFieldValue($field, $document);

        if ($value === []) {
            return [];
        }

        return $this->generateEmbeddings($field, $value, $document);
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
        if (!str_contains($field->fullPath, '.')) {
            return null;
        }

        $parts = explode('.', $field->fullPath);
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
        return $field->parentType === Nested::class && str_contains($field->fullPath, '.');
    }

    protected function extractNestedValue(Text|Image $field, Document $document, string $nestedPath): array
    {
        $parentArray = dot($document->_source)->get($nestedPath);

        if (!$parentArray || !is_array($parentArray)) {
            return [];
        }

        // Get the relative path from the nested field to this field
        $relativePath = substr($field->fullPath, strlen($nestedPath) + 1);

        // Check if this is a single object (associative array) or array of objects (indexed array)
        // Single object: ['key' => 'value'], Array of objects: [['key' => 'value'], ['key' => 'value']]
        $isIndexedArray = array_keys($parentArray) === range(0, count($parentArray) - 1);

        if (!$isIndexedArray) {
            // Single object - extract value directly
            $value = dot($parentArray)->get($relativePath);
            return $value !== null ? (is_array($value) ? $value : [$value]) : [];
        }

        // Array of objects - map over each item
        return (new Collection($parentArray))
            ->map(fn($item) => dot($item)->get($relativePath))
            ->filter(fn($value): bool => $value !== null)
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
        $value = dot($document->_source)->get($field->fullPath);

        if (!$value) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    protected function generateEmbeddings(Text|Image $field, array $value, Document $document): array
    {
        // Get the appropriate API for this field
        $embeddingsApi = $this->getEmbeddingsApiForField($field);

        // Handle images separately from text
        if ($field instanceof Image) {
            return $this->generateImageEmbeddings($field, $value, $embeddingsApi, $document);
        }

        // Original text processing
        $fieldVectors = $this->prepareVectorFields($field->vectorFields(), $value);

        $vectorsCollection = new Collection($fieldVectors);

        $nameStrategy = $vectorsCollection->mapWithKeys(fn($item) => [$item['name'] => $item['strategy']]);

        // Collect normalize settings for each vector
        $normalizeSettings = [];
        foreach ($field->vectorFields() as $vectorField) {
            $vector = $vectorField instanceof Nested ? $vectorField->properties['vector'] : $vectorField;
            if ($vector instanceof BaseVector) {
                $normalizeSettings[$vectorField->name] = $vector->autoNormalizeVector();
            } else {
                $normalizeSettings[$vectorField->name] = true; // Default to normalize
            }
        }

        $valuesToEmbed = $vectorsCollection
            ->map(fn($item) => $item['vectors'])
            ->flatten(1)
            ->values();

        $embeddedVectors = $embeddingsApi->batchEmbed($valuesToEmbed);

        $formattedVectors = $this->formatEmbeddedVectors($embeddedVectors, $nameStrategy, $normalizeSettings);

        // Apply boost scaling if needed
        return $this->applyBoostScaling($field, $formattedVectors, $document);
    }

    protected function generateImageEmbeddings(Image $field, array $imageUrls, EmbeddingsApi $embeddingsApi, Document $document): array
    {
        $result = [];

        foreach ($field->vectorFields() as $vectorField) {
            $vector = $vectorField instanceof Nested ? $vectorField->properties['vector'] : $vectorField;
            $name = $vectorField->name;
            $dimensions = $vector->dims();
            $strategy = $vector->strategy();

            // Determine if we should normalize
            $normalize = true;
            if ($vector instanceof BaseVector) {
                $normalize = $vector->autoNormalizeVector();
            }

            // Prepare image values using strategy
            $preparedImages = $strategy->prepare($imageUrls);

            // Embed each image individually using embed() method
            $embeddings = [];
            foreach ($preparedImages as $imageUrl) {
                $embedding = $embeddingsApi->embed($imageUrl, $dimensions);
                $embeddings[] = $embedding;
            }

            // Format embeddings according to strategy
            $result[$name] = $strategy->format($embeddings, $normalize);
        }

        // Apply boost scaling if needed
        return $this->applyBoostScaling($field, $result, $document);
    }

    protected function formatEmbeddedVectors(array $embeddedVectors, Collection $nameStrategy, array $normalizeSettings = []): array
    {
        return (new Collection($embeddedVectors))
            ->groupBy('name')
            ->mapWithKeys(function ($group, $name) use ($nameStrategy, $normalizeSettings) {
                $strategy = $nameStrategy->get($name);
                $normalize = $normalizeSettings[$name] ?? true;

                $vectors = (new Collection($group))
                    ->map(fn($item) => $item['vector'] ?? [])
                    ->toArray();

                $formatted = $strategy->format($vectors, $normalize);

                return [$name => $formatted];
            })
            ->toArray();
    }

    protected function prepareVectorFields(Collection $vectorFields, array $value): array
    {
        return $vectorFields
            ->map(fn($vector): array => $this->prepareVectorTexts($vector, $value))
            ->flatten(2)
            ->groupBy('name')
            ->mapWithKeys(fn($group, $groupName): array => $this->groupVectorsByName($group, $groupName))
            ->toArray();
    }

    protected function prepareVectorTexts(BaseVector|NestedVector $vector, array $value): array
    {
        $name = $vector->name;

        if ($vector instanceof NestedVector) {
            $vector = $vector->properties['vector'];
        }

        $preparedTexts = $vector->strategy()->prepare($value);

        return [
            array_map(fn($text): array => [
                'name' => $name,
                'text' => $text,
                'strategy' => $vector->strategy(),
                'dims' => (string) $vector->dims(),
            ], $preparedTexts),
        ];
    }

    protected function groupVectorsByName(array $group, string $groupName): array
    {
        $groupCollection = new Collection($group);

        return [
            $groupName => [
                'name' => $groupName,
                'strategy' => $groupCollection->first()['strategy'],
                'vectors' => $groupCollection
                    ->map(fn($item): array => [
                        'name' => $groupName,
                        'text' => $item['text'],
                        'dims' => $item['dims'],
                        'vector' => [],
                    ])
                    ->toArray(),
            ]
        ];
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

    protected function getEmbeddingsApiForField(Text|Image $field): EmbeddingsApi
    {
        // Check if any vector field has a specific API configured
        foreach ($field->vectorFields()->getIterator() as $vectorField) {

            return $this->getApi($vectorField->apiName);
        }
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
            return array_map(fn($item): mixed => $this->formatDateTimeValue($item, $field), $value);
        }

        return $value;
    }

    protected function validateFieldValue(string $fieldPath, mixed $value, Type $field, array &$errors): void
    {
        // For Range fields, the array IS the value (e.g., ['gt' => 10, 'lt' => 20])
        // Don't iterate over it like we do for other field types
        if ($field instanceof Range) {
            [$isValid, $errorMessage] = $field->validate($fieldPath, $value);

            if (!$isValid) {
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

        if (!$isValid) {
            $errors[] = $errorMessage;
        }
    }

    protected function applyBoostScaling(Text|Image $field, array $formattedVectors, Document $document): array
    {
        foreach ($field->vectorFields() as $vectorField) {
            $vector = $vectorField instanceof Nested ? $vectorField->properties['vector'] : $vectorField;

            // Only SigmieVector has boost support
            if (!($vector instanceof BaseVector)) {
                continue;
            }

            $boostedByField = $vector->boostedByField();

            if ($boostedByField === null) {
                continue;
            }

            // Validate boost field
            $this->validateBoostField($field->fullPath, $boostedByField);

            // Extract boost value from document
            $boostValue = dot($document->_source)->get($boostedByField);

            if ($boostValue === null) {
                throw new Exception(sprintf("Boost field '%s' is not present in document for semantic field '%s'", $boostedByField, $field->fullPath));
            }

            if (!is_numeric($boostValue) || $boostValue <= 0) {
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

        if (!($field instanceof Number)) {
            throw new Exception(sprintf("Boost field '%s' must be a Number type (float/double/integer). Got: ", $boostField) . $field->typeName());
        }
    }
}
