<?php

declare(strict_types=1);

namespace Sigmie\Semantic;

use Carbon\Carbon;
use DateTime as PHPDateTime;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Document\Document;
use Sigmie\Helpers\ImageHelper;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Combo;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Image;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Shared\Collection;
use Sigmie\Shared\UsesApis;

class DocumentProcessor
{
    use UsesApis;

    public function __construct(
        protected Properties $properties
    ) {}

    public function populateComboFields(Document $document): Document
    {
        $comboFields = $this->getComboFields();

        $comboFields->each(function (Combo $field) use ($document) {
            $value = $this->buildComboValue($field, $document);

            if (!empty($value)) {
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
            ->filter(fn($vectors) => !empty($vectors))
            ->toArray();

        $document['embeddings'] = $this->buildNestedStructure($embeddings);

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
            ->filter(fn(Text $field) => $field instanceof Combo);
    }

    protected function buildComboValue(Combo $field, Document $document): array
    {
        return (new Collection($field->sourceFields()))
            ->map(fn($sourceField) => $document->get($sourceField))
            ->filter(fn($value) => $value !== null)
            ->flatMap(fn($value) => is_array($value) ? $value : [$value])
            ->toArray();
    }

    protected function processField(Text|Image $field, Document $document): array
    {
        // Check if embeddings already exist for this field
        $existingEmbeddings = dot($document->_source)->get("embeddings.{$field->fullPath}");

        if ($existingEmbeddings && is_array($existingEmbeddings)) {
            // Check if all required vector fields already have embeddings
            $vectorFields = $field->vectorFields();
            $allExist = $vectorFields->every(function ($vectorField) use ($existingEmbeddings) {
                $name = $vectorField instanceof Nested ? $vectorField->name : $vectorField->name;
                return isset($existingEmbeddings[$name]) && !empty($existingEmbeddings[$name]);
            });

            if ($allExist) {
                // Return existing embeddings, no need to regenerate
                return $existingEmbeddings;
            }
        }

        $value = $this->extractFieldValue($field, $document);

        if (empty($value)) {
            return [];
        }

        return $this->generateEmbeddings($field, $value);
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
        for ($i = 1; $i < count($parts); $i++) {
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
            ->filter(fn($value) => $value !== null)
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

    protected function generateEmbeddings(Text|Image $field, array $value): array
    {
        // Get the appropriate API for this field
        $embeddingsApi = $this->getEmbeddingsApiForField($field);

        // Handle images separately from text
        if ($field instanceof Image) {
            return $this->generateImageEmbeddings($field, $value, $embeddingsApi);
        }

        // Original text processing
        $fieldVectors = $this->prepareVectorFields($field->vectorFields(), $value);

        $vectorsCollection = new Collection($fieldVectors);

        $nameStrategy = $vectorsCollection->mapWithKeys(fn($item) => [$item['name'] => $item['strategy']]);

        $valuesToEmbed = $vectorsCollection
            ->map(fn($item) => $item['vectors'])
            ->flatten(1)
            ->values();

        $embeddedVectors = $embeddingsApi->batchEmbed($valuesToEmbed);

        return $this->formatEmbeddedVectors($embeddedVectors, $nameStrategy);
    }

    protected function generateImageEmbeddings(Image $field, array $imageUrls, EmbeddingsApi $embeddingsApi): array
    {
        $result = [];

        foreach ($field->vectorFields() as $vectorField) {
            $vector = $vectorField instanceof Nested ? $vectorField->properties['vector'] : $vectorField;
            $name = $vectorField->name;
            $dimensions = $vector->dims();
            $strategy = $vector->strategy();

            // Prepare image values using strategy
            $preparedImages = $strategy->prepare($imageUrls);

            // Embed each image individually using embed() method
            $embeddings = [];
            foreach ($preparedImages as $imageUrl) {
                $embedding = $embeddingsApi->embed($imageUrl, $dimensions);
                $embeddings[] = $embedding;
            }

            // Format embeddings according to strategy
            $result[$name] = $strategy->format($embeddings);
        }

        return $result;
    }

    protected function formatEmbeddedVectors(array $embeddedVectors, Collection $nameStrategy): array
    {
        return (new Collection($embeddedVectors))
            ->groupBy('name')
            ->mapWithKeys(function ($group, $name) use ($nameStrategy) {
                $strategy = $nameStrategy->get($name);

                $vectors = (new Collection($group))
                    ->map(fn($item) => $item['vector'] ?? [])
                    ->toArray();

                return [$name => $strategy->format($vectors)];
            })
            ->toArray();
    }

    protected function prepareVectorFields(Collection $vectorFields, array $value): array
    {
        return $vectorFields
            ->map(fn($vector) => $this->prepareVectorTexts($vector, $value))
            ->flatten(2)
            ->groupBy('name')
            ->mapWithKeys(fn($group, $groupName) => $this->groupVectorsByName($group, $groupName))
            ->toArray();
    }

    protected function prepareVectorTexts(Nested|DenseVector $vector, array $value): array
    {
        $name = $vector->name;

        if ($vector instanceof Nested) {
            $vector = $vector->properties['vector'];
        }

        $preparedTexts = $vector->strategy()->prepare($value);

        return [
            array_map(fn($text) => [
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
                    ->map(fn($item) => [
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

            $api = $this->getApi($vectorField->apiName);

            return $api;
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
            return array_map(fn($item) => $this->formatDateTimeValue($item, $field), $value);
        }

        return $value;
    }

    protected function validateFieldValue(string $fieldPath, mixed $value, Type $field, array &$errors): void
    {
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
}
