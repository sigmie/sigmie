<?php

declare(strict_types=1);

namespace Sigmie\Semantic;

use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Sigmie\Document\Document;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Contracts\FieldContainer;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\Combo;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\FlatObject;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\Image;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\SearchableNumber;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Shared\Collection;
use Sigmie\Shared\UsesApis;
use Sigmie\Support\VectorMath;
use stdClass;
use Stringable;
use UnexpectedValueException;

use function Sigmie\Functions\source_field_paths;
use function Sigmie\Functions\trim_text;
use function Sigmie\Functions\with_minute_offset;

class DocumentProcessor
{
    use UsesApis;

    public const DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        protected Properties $properties,
        protected SearchEngineType $engine = SearchEngineType::Elasticsearch,
    ) {}

    public function populateComboFields(Document $document, bool $partial = false): Document
    {
        $this->properties->fieldsOfType(Combo::class)->each(function (Combo $field) use ($document, $partial): void {
            $this->writeComboField($field, $document, $partial);
        });

        return $document;
    }

    public function patchNeedsCurrentSource(Document $patch, bool $populateEmbeddings): bool
    {
        $source = $patch->_source;

        $combos = $this->properties->fieldsOfType(Combo::class);
        foreach ($combos as $field) {
            $paths = $this->comboSourcePaths($field);
            if ($combos->count() === 1 && count($paths) === 1 && ! str_contains($paths[0], '.')
                && $this->properties->get($paths[0]) instanceof Text && $this->boostPaths($field) === []) {
                continue;
            }

            foreach ($paths as $path) {
                if ($this->sourceValues($source, $path, $this->properties, documentRoot: true) !== []) {
                    return true;
                }
            }
        }

        foreach ($this->properties->fieldsOfType(Range::class)->merge($this->properties->fieldsOfType(GeoPoint::class)) as $field) {
            foreach ($this->sourceValues($source, $field->fullPath(), $this->properties, documentRoot: true) as $value) {
                if ($value instanceof stdClass || (is_array($value) && ! array_is_list($value))) {
                    return true;
                }
            }
        }

        if (! $populateEmbeddings || ! $this->hasApi()) {
            return false;
        }

        foreach ($this->properties->nestedSemanticFields() as $field) {
            foreach ($this->boostPaths($field) as $path) {
                if ($this->sourceValues($source, $path, $this->properties, documentRoot: true) !== []
                    || $this->sourceValues($source, $field->fullPath(), $this->properties, documentRoot: true) !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Apply the source patch before deriving values that depend on stored fields.
     *
     * @return list<string> Semantic fields to generate from the merged source
     */
    public function applyPatch(Document $patch, array $current, bool $populateEmbeddings): array
    {
        $changes = $patch->_source;
        $source = $this->mergeSource($current, (array) $this->properties->sourceObject($changes), $this->properties);
        $patch->_source = $source;

        foreach ($this->properties->fieldsOfType(Combo::class) as $field) {
            foreach ($this->comboSourcePaths($field) as $path) {
                if ($this->sourceValues($changes, $path, $this->properties, documentRoot: true) !== []
                    || $this->sourceValues($current, $path, $this->properties, documentRoot: true)
                        !== $this->sourceValues($patch->_source, $path, $this->properties, documentRoot: true)) {
                    $this->writeComboField($field, $patch, partial: false);

                    break;
                }
            }
        }

        if (! $populateEmbeddings || ! $this->hasApi()) {
            return [];
        }

        $updatedFields = [];
        foreach ($this->properties->nestedSemanticFields() as $field) {
            $path = $field->fullPath();
            $inputsChanged = $this->sourceValues($changes, $path, $this->properties, documentRoot: true) !== []
                || $this->sourceValues($current, $path, $this->properties, documentRoot: true)
                    !== $this->sourceValues($patch->_source, $path, $this->properties, documentRoot: true);
            foreach ($this->boostPaths($field) as $boost) {
                $inputsChanged = $inputsChanged || $this->sourceValues($changes, $boost, $this->properties, documentRoot: true) !== [];
            }

            if ($inputsChanged) {
                $updatedFields[] = $path;
            }
        }

        return $updatedFields;
    }

    private function comboSourcePaths(Combo $field): array
    {
        $full = $field->fullPath();
        $parent = substr($full, 0, strlen($full) - strlen($field->name));

        return array_map(
            fn (string $path): string => str_starts_with($path, $parent) ? $path : $parent.$path,
            $field->sourceFields(),
        );
    }

    private function boostPaths(Text $field): array
    {
        $paths = [];
        foreach ($field->vectorFields() as $vectorField) {
            $vector = $vectorField instanceof Nested ? $vectorField->properties['vector'] : $vectorField;
            if ($vector->boostedByField() !== null) {
                $paths[] = $vector->boostedByField();
            }
        }

        return $paths;
    }

    private function mergeSource(array $source, array $patch, ?Properties $properties = null): array
    {
        foreach ($patch as $key => $value) {
            $field = $properties?->get((string) $key);
            $previous = $source[$key] ?? null;
            if (! $field instanceof Range && ! $field instanceof GeoPoint
                && ($value instanceof stdClass || (is_array($value) && ! array_is_list($value)))
                && ($previous instanceof stdClass || (is_array($previous) && ! array_is_list($previous)))) {
                $children = $field instanceof FieldContainer ? $field->getProperties() : null;
                $merged = $this->mergeSource((array) $previous, (array) $value, $children);
                $value = array_is_list($merged) ? (object) $merged : $merged;
            }
            $source[$key] = $value;
        }

        return $source;
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
     * @param  array<int|string, Document>  $documents
     * @param  array<int|string, array{fields: list<string>, embeddings: Document}>  $patches
     * @return array<int|string, Document>
     */
    public function populateEmbeddingsBatch(array $documents, array $patches = []): array
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
            $patch = $patches[$docIdx] ?? null;
            foreach ($semanticFields as $field) {
                $path = $field->fullPath();
                if ($patch !== null && ! in_array($path, $patch['fields'], true)) {
                    continue;
                }

                $reused = $this->reuseExistingEmbeddings($field, $patch['embeddings'] ?? $document);

                if ($reused !== null) {
                    $existingByDoc[$docIdx][$field->fullPath()] = $reused;

                    continue;
                }

                $sourceValues = $this->sourceValues($document->_source, $field->fullPath(), $this->properties, documentRoot: true);
                $inputs = $sourceValues;
                if ($field->type() === 'completion') {
                    $inputs = [];
                    foreach ($sourceValues as $value) {
                        $entries = is_array($value) && array_is_list($value) ? $value : [$value];
                        foreach ($entries as $entry) {
                            $inputs[] = is_array($entry) || $entry instanceof stdClass
                                ? ((array) $entry)['input'] ?? null
                                : $entry;
                        }
                    }
                }

                $value = (new Collection($inputs))->flatten()
                    ->filter(fn (mixed $value): bool => $value !== null && (! is_string($value) || trim_text($value) !== ''))
                    ->values();

                if ($value === []) {
                    if ($patch !== null || $sourceValues !== []) {
                        $existingByDoc[$docIdx][$field->fullPath()] = null;
                    }

                    continue;
                }

                foreach ($this->planFieldWorkItems($field, $value, $docIdx) as $item) {
                    $workItems[] = $item;
                }
            }
        }

        $workItems = $this->runBatches($workItems);

        return $this->assembleEmbeddings($documents, $workItems, $existingByDoc, $patches);
    }

    protected function reuseExistingEmbeddings(Text|Image $field, Document $document): ?array
    {
        $existingEmbeddings = $document->get('_embeddings.'.$field->fullPath());

        if ($existingEmbeddings instanceof stdClass) {
            $existingEmbeddings = (array) $existingEmbeddings;
        }

        if (! is_array($existingEmbeddings) || $existingEmbeddings === []) {
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

    protected function planFieldWorkItems(Text|Image $field, array $value, int|string $docIdx): array
    {
        if ($field instanceof SearchableNumber) {
            $value = array_map('strval', $value);
        }

        $items = [];
        $isImage = $field instanceof Image;

        foreach ($field->vectorFields() as $vectorField) {
            $vector = $vectorField instanceof Nested ? $vectorField->properties['vector'] : $vectorField;
            $strategy = $vector->strategy();
            if ($isImage && $strategy === VectorStrategy::Concatenate) {
                $strategy = VectorStrategy::Average;
            }

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
                    $vector = $result[$pos]['vector'] ?? null;
                    if (! is_array($vector) || $vector === []) {
                        throw new UnexpectedValueException(sprintf("Embeddings API '%s' returned no vector for batch item %d", $workItems[$i]['api_name'], $pos));
                    }

                    $workItems[$i]['vector'] = $vector;
                }
            }
        }

        return $workItems;
    }

    protected function assembleEmbeddings(array $documents, array $workItems, array $existingByDoc, array $patches = []): array
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

            if ($flatEmbeddings !== []) {
                $embeddings = $this->buildNestedStructure($flatEmbeddings);
                $document['_embeddings'] = isset($patches[$docIdx])
                    ? $this->mergeSource((array) $document['_embeddings'], $embeddings)
                    : $embeddings;
            }

            $documents[$docIdx] = $document;
        }

        return $documents;
    }

    public function formatDateTimeFields(Document $document): Document
    {
        $source = $document->_source;

        foreach ($this->properties->fieldNames(withParent: false) as $fieldPath) {
            $field = $this->properties->get($fieldPath);

            if (! $field) {
                continue;
            }

            $source = $this->formatDateTimeAt($source, $fieldPath, $field, $this->properties);
        }

        $document->_source = $source;

        return $document;
    }

    protected function formatDateTimeAt(array $source, string $path, Type $field, Properties $properties): array
    {
        foreach (source_field_paths($source, $path) as $key => $remaining) {
            if ($remaining === '') {
                $source[$key] = $this->formatDateTimeValue($source[$key], $field);

                continue;
            }

            $container = $properties->get($key);
            if (! $container instanceof FieldContainer || ($container instanceof Object_ && ! $container->isEnabled())) {
                continue;
            }

            $children = $container->getProperties();
            $source[$key] = $this->mapRecords(
                $source[$key],
                $children,
                fn (array $record): array => $this->formatDateTimeAt($record, $remaining, $field, $children),
            );
        }

        return $source;
    }

    private function mapRecords(mixed $value, Properties $properties, callable $transform): mixed
    {
        $map = static function (mixed $record) use ($transform): mixed {
            if ($record instanceof stdClass) {
                return (object) $transform((array) $record);
            }

            return is_array($record) ? $transform($record) : $record;
        };

        return is_array($value) && ! $properties->isSourceObject($value)
            ? array_map($map, $value)
            : $map($value);
    }

    public function validateFields(Document $document): Document
    {
        $errors = [];

        foreach ($this->properties->fieldNames(withParent: true) as $fieldPath) {
            $field = $this->properties->get($fieldPath);

            if (! $field) {
                continue;
            }

            foreach ($this->sourceValues($document->_source, $fieldPath, $this->properties, documentRoot: true) as $value) {
                $this->validateFieldValue($fieldPath, $value, $field, $errors);
            }
        }

        if (! empty($errors)) {
            throw new InvalidArgumentException(
                'Document validation failed: '.implode(', ', $errors)
            );
        }

        return $document;
    }

    /**
     * dot() misses a path through a nested list. Each item is read on its own.
     * The value at the end of the path stays whole, including a range or geo object.
     *
     * @return list<mixed>
     */
    private function sourceValues(mixed $node, string $path, ?Properties $properties, bool $documentRoot = false): array
    {
        if (! is_array($node) && ! $node instanceof stdClass) {
            return $node === null ? [null] : [];
        }

        $values = [];
        if (! $documentRoot && is_array($node) && array_is_list($node) && ! $properties?->isSourceObject($node)) {
            foreach ($node as $item) {
                array_push($values, ...$this->sourceValues($item, $path, $properties, documentRoot: true));
            }

            // A list replaces its previous records during a partial update.
            return $values === [] ? [null] : $values;
        }

        $node = (array) $node;
        foreach (source_field_paths($node, $path) as $key => $remaining) {
            if ($remaining === '') {
                $values[] = $node[$key];

                continue;
            }

            $container = $properties?->get($key);
            if ($container instanceof Object_ && ! $container->isEnabled()) {
                continue;
            }

            $children = $container instanceof FieldContainer ? $container->getProperties() : null;
            array_push($values, ...$this->sourceValues($node[$key], $remaining, $children));
        }

        return $values;
    }

    private function writeComboField(Combo $field, Document $document, bool $partial): void
    {
        $full = $field->fullPath();
        $leaf = $field->name;
        if ($full === $leaf) {
            $document->_source = $this->withComboValue($document->_source, $leaf, $field, '', partial: $partial);

            return;
        }

        $parent = substr($full, 0, -strlen($leaf) - 1);

        $document->_source = $this->writeComboAt(
            $document->_source,
            $parent,
            $field,
            $parent,
            $this->properties,
            $partial,
        );
    }

    private function writeComboAt(array $node, string $path, Combo $field, string $parent, Properties $properties, bool $partial): array
    {
        if ($path === '') {
            return $this->withComboValue($node, $field->name, $field, $parent, partial: $partial);
        }

        foreach (source_field_paths($node, $path) as $key => $remaining) {
            $container = $properties->get($key);
            if (! $container instanceof FieldContainer) {
                continue;
            }

            $children = $container->getProperties();
            $partialRecord = $partial && ($node[$key] instanceof stdClass || (is_array($node[$key]) && $children->isSourceObject($node[$key])));
            $node[$key] = $this->mapRecords(
                $node[$key],
                $children,
                fn (array $record): array => $this->writeComboAt($record, $remaining, $field, $parent, $children, $partialRecord),
            );
        }

        // A dotted sibling can represent the parent without a container in _source.
        $prefix = $path.'.';
        $siblings = [];
        foreach ($node as $key => $value) {
            if (str_starts_with((string) $key, $prefix)) {
                $siblings[substr($key, strlen($prefix))] = $value;
            }
        }

        return $siblings === []
            ? $node
            : $this->withComboValue($node, $prefix.$field->name, $field, $parent, $siblings, $partial);
    }

    private function withComboValue(array $node, string $leaf, Combo $field, string $parent, ?array $context = null, bool $partial = false): array
    {
        [$text, $allSourcesPresent] = $this->comboText($field->sourceFields(), $context ?? $node, $parent);
        if ($text !== '') {
            $node[$leaf] = $text;
        } elseif ($partial && $allSourcesPresent) {
            // A partial update merges into the stored document. Only null clears its combo.
            $node[$leaf] = null;
        } else {
            unset($node[$leaf]);
        }

        return $node;
    }

    /**
     * @param  list<string>  $sourceFields
     * @return array{string, bool}
     */
    private function comboText(array $sourceFields, array $context, string $parent): array
    {
        $parts = [];
        $properties = $parent === '' ? $this->properties : $this->properties->get($parent)->getProperties();
        $allSourcesPresent = $sourceFields !== [];

        foreach ($sourceFields as $sourceField) {
            $relative = $sourceField;

            if ($parent !== '' && str_starts_with($sourceField, $parent.'.')) {
                $relative = substr($sourceField, strlen($parent) + 1);
            }

            $values = $this->sourceValues($context, $relative, $properties, documentRoot: true);
            $allSourcesPresent = $allSourcesPresent && $values !== [];
            $this->appendComboParts($values, $parts);
        }

        return [implode(' ', $parts), $allSourcesPresent];
    }

    private function appendComboParts(mixed $value, array &$parts): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_object($value) && ! $value instanceof Stringable) {
            $value = get_object_vars($value);
        }

        if (! is_array($value)) {
            $parts[] = (string) $value;

            return;
        }

        foreach ($value as $item) {
            $this->appendComboParts($item, $parts);
        }
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
        if ($value instanceof DateTimeInterface) {
            if ($field instanceof Date) {
                return $field->formatForWrite($value, 'Y-m-d');
            }

            if ($field instanceof Range && $field->typeName() === 'date_range') {
                return $field->formatDateBound($value);
            }

            if ($field instanceof DateTime) {
                return $field->formatForWrite(with_minute_offset($value), 'Y-m-d\TH:i:s.uP');
            }
        }

        if ($value instanceof stdClass && $field instanceof Range) {
            return (object) $this->formatDateTimeValue((array) $value, $field);
        }

        if (is_array($value)) {
            return array_map(fn ($item): mixed => $this->formatDateTimeValue($item, $field), $value);
        }

        return $value;
    }

    protected function validateFieldValue(string $fieldPath, mixed $value, Type $field, array &$errors): void
    {
        if ($value === null) {
            return;
        }

        $structured = $field instanceof GeoPoint || $field instanceof Nested || $field instanceof Object_ || $field instanceof FlatObject
            || $field instanceof BaseVector || $field instanceof DenseVector
            || ($field instanceof Text && $field->type() === 'completion');
        // A sparse integer-keyed array is sent as a list of its values.
        if (is_array($value) && array_filter(array_keys($value), is_string(...)) === [] && ! $structured) {
            foreach ($value as $item) {
                $this->validateFieldValue($fieldPath, $item, $field, $errors);
            }

            return;
        }

        [$isValid, $errorMessage] = $field instanceof FlatObject
            ? $field->validate($fieldPath, $value, $this->engine)
            : $field->validate($fieldPath, $value);

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

            $values = $this->sourceValues($document->_source, $boostedByField, $this->properties, documentRoot: true);
            $boostValue = count($values) === 1 ? $values[0] : null;

            if ($boostValue === null) {
                throw new Exception(sprintf("Boost field '%s' is not present in document for semantic field '%s'", $boostedByField, $field->fullPath()));
            }

            if (is_array($boostValue) && count($boostValue) === 1) {
                $boostValue = reset($boostValue);
            }

            if (! is_numeric($boostValue) || $boostValue <= 0) {
                throw new Exception(sprintf("Boost field '%s' must be a positive number. Got: %s", $boostedByField, $boostValue));
            }

            // Preserve direction when the boost is below the normal float32 range.
            if ($vector->autoNormalizeVector() && $boostValue < 2 ** -126) {
                continue;
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
                    $formattedVectors[$name][$index]['vector'] = VectorMath::scale($item['vector'], (float) $boostValue);
                }
            } else {
                // Regular format: flat array of numbers
                $formattedVectors[$name] = VectorMath::scale($currentVector, (float) $boostValue);
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

        if (! $field instanceof Number && ! $field instanceof Price) {
            throw new Exception(sprintf("Boost field '%s' must be a Number type (float/double/integer). Got: ", $boostField).$field->typeName());
        }
    }
}
