<?php

declare(strict_types=1);

namespace Sigmie\Semantic;

use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Document\Document;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Combo;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Text;
use Sigmie\Shared\Collection;

class DocumentProcessor
{
    public function __construct(
        protected Properties $properties,
        protected ?EmbeddingsApi $embeddingsApi = null,
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
        if (!$this->embeddingsApi) {
            return $document;
        }

        $embeddings = $this->properties
            ->nestedSemanticFields()
            ->mapWithKeys(fn(Text $field) => [
                $field->fullPath => $this->processField($field, $document)
            ])
            ->filter(fn($vectors) => !empty($vectors))
            ->toArray();

        $document['embeddings'] = $this->buildNestedStructure($embeddings);

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

    protected function processField(Text $field, Document $document): array
    {
        $value = $this->extractFieldValue($field, $document);

        if (empty($value)) {
            return [];
        }

        return $this->generateEmbeddings($field, $value);
    }

    protected function extractFieldValue(Text $field, Document $document): array
    {
        $nestedAncestor = $this->findNestedAncestor($field);

        if ($nestedAncestor) {
            return $this->extractNestedValue($field, $document, $nestedAncestor);
        }

        return $this->extractSimpleValue($field, $document);
    }

    protected function findNestedAncestor(Text $field): ?string
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

    protected function isNestedField(Text $field): bool
    {
        return $field->parentType === Nested::class && str_contains($field->fullPath, '.');
    }

    protected function extractNestedValue(Text $field, Document $document, string $nestedPath): array
    {
        $parentArray = dot($document->_source)->get($nestedPath);

        if (!$parentArray || !is_array($parentArray)) {
            return [];
        }

        // Get the relative path from the nested field to this field
        $relativePath = substr($field->fullPath, strlen($nestedPath) + 1);

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

    protected function extractSimpleValue(Text $field, Document $document): array
    {
        $value = dot($document->_source)->get($field->fullPath);

        if (!$value) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    protected function generateEmbeddings(Text $field, array $value): array
    {
        $fieldVectors = $this->prepareVectorFields($field->vectorFields(), $value);

        $vectorsCollection = new Collection($fieldVectors);

        $nameStrategy = $vectorsCollection->mapWithKeys(fn($item) => [$item['name'] => $item['strategy']]);

        $valuesToEmbed = $vectorsCollection
            ->map(fn($item) => $item['vectors'])
            ->flatten(1)
            ->values();

        $embeddedVectors = $this->embeddingsApi->batchEmbed($valuesToEmbed);

        return $this->formatEmbeddedVectors($embeddedVectors, $nameStrategy);
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
}
