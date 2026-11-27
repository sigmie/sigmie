<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use ArrayAccess;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Index\Analysis\BuiltInAnalyzer;
use Sigmie\Index\Analysis\SimpleAnalyzer;
use Sigmie\Index\Analysis\Standard;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Mappings\Contracts\FieldContainer;
use Sigmie\Mappings\Contracts\Type as ContractsType;
use Sigmie\Mappings\Types\Address;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Boost;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Category;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Email;
use Sigmie\Mappings\Types\FlatObject;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\HTML;
use Sigmie\Mappings\Types\Id;
use Sigmie\Mappings\Types\Image;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\KnnVector;
use Sigmie\Mappings\Types\LongText;
use Sigmie\Mappings\Types\Name;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Path;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\SearchableNumber;
use Sigmie\Mappings\Types\ShortText;
use Sigmie\Mappings\Types\Tags;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Title;
use Sigmie\Mappings\Types\Type;
use Sigmie\Shared\Collection;
use stdClass;

use function Sigmie\Functions\declared_list;
use function Sigmie\Functions\path_key;

class Properties extends Type implements ArrayAccess, FieldContainer
{
    public const ROOT_NAME = 'mappings';

    public ?Boost $boostField = null;

    public function __construct(string $name = self::ROOT_NAME, protected array $fields = [])
    {
        parent::__construct($name);

        $this->type = ElasticsearchMappingType::PROPERTIES->value;
        $this->setFieldPaths($this->isRoot() ? '' : $this->name);
        $this->refreshBoostField();
    }

    public function queries(array|string $queryString): array
    {
        return [];
    }

    public function copy(): self
    {
        return clone $this;
    }

    public function __clone(): void
    {
        $this->fields = array_map(static fn (Type $field): Type => clone $field, $this->fields);
        $this->refreshBoostField();
    }

    private function refreshBoostField(): void
    {
        $this->boostField = null;
        if ($this->isRoot()) {
            foreach ($this->fields as $field) {
                if ($field instanceof Boost) {
                    $this->boostField = $field;

                    return;
                }
            }
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->fields[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->fields[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->assignFieldPath($value);
        $this->fields[$offset ?? $value->name] = $value;
        $this->refreshBoostField();
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->fields[$offset]);
        $this->refreshBoostField();
    }

    public function textFields(): Collection
    {
        $collection = new Collection($this->fields);

        return $collection->filter(fn (ContractsType $type): bool => $type instanceof Text);
    }

    public function imageFields(): Collection
    {
        $collection = new Collection($this->fields);

        return $collection->filter(fn (ContractsType $type): bool => $type instanceof Image);
    }

    public function deepFields(): Collection
    {
        $collection = new Collection($this->fields);

        return $collection->filter(fn (Type $type): bool => $type instanceof FieldContainer);
    }

    public function embeddingsFields(): Collection
    {
        return $this->textFields()->filter(fn (Text $field): bool => $field->isSemantic());
    }

    public function completionFields(): Collection
    {
        return $this->fieldsOfType(Text::class)
            ->filter(fn (Text $text): bool => $text->type() === 'completion');
    }

    public function toArray(): array
    {
        return $this->fields;
    }

    public function isSourceObject(array $value): bool
    {
        if (! array_is_list($value)) {
            return true;
        }

        if (! declared_list($value, $this->fields)) {
            return false;
        }

        // Prefer a list of records when every item could be an object in this mapping.
        if (! $this->isSourceObjectList($value)) {
            return true;
        }

        return array_filter($value, fn (mixed $item): bool => $item !== null) === [];
    }

    public function isSourceObjectList(array $value): bool
    {
        if (! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($item === null) {
                continue;
            }

            if (! $item instanceof stdClass && (
                ! is_array($item) || ($item !== [] && array_is_list($item) && ! declared_list($item, $this->fields))
            )) {
                return false;
            }
        }

        return true;
    }

    public function sourceObject(array $source): array|stdClass
    {
        foreach ($source as $name => $value) {
            $field = $this->get((string) $name);
            if ($name === '_embeddings' && ! $field instanceof Type && is_array($value) && $value !== []) {
                $source[$name] = $this->embeddingSourceObject($value);

                continue;
            }

            if ($field instanceof Object_ && ! $field->isEnabled()) {
                continue;
            }

            if ($field instanceof FlatObject && is_array($value) && array_is_list($value)) {
                $source[$name] = FlatObject::sourceRecords($value);

                continue;
            }

            if ($field instanceof Type && ! $field instanceof Field && ! $field instanceof FieldContainer && ! $field instanceof FlatObject
                && is_array($value) && ! array_is_list($value) && array_keys($value) === array_filter(array_keys($value), 'is_int')) {
                $source[$name] = array_values($value);

                continue;
            }

            if (! $field instanceof FieldContainer) {
                continue;
            }

            if (! is_array($value) && ! $value instanceof stdClass) {
                continue;
            }

            $properties = $field->getProperties();
            if ($value instanceof stdClass || $properties->isSourceObject($value)) {
                $source[$name] = $properties->sourceObject((array) $value);

                continue;
            }

            $source[$name] = array_map(
                fn (mixed $item): mixed => is_array($item) || $item instanceof stdClass
                    ? $properties->sourceObject((array) $item)
                    : $item,
                $value,
            );
        }

        return array_is_list($source) ? (object) $source : $source;
    }

    private function embeddingSourceObject(array $source): array|stdClass
    {
        // The embedding tree follows field paths. A nested source list has one shared embedding object.
        foreach ($source as $name => $value) {
            $field = $this->fields[$name] ?? null;
            if ($field instanceof FieldContainer && is_array($value)) {
                $source[$name] = $field->getProperties()->embeddingSourceObject($value);
            }
        }

        return array_is_list($source) ? (object) $source : $source;
    }

    public function handleCustomAnalyzers(AnalysisInterface $analysis): void
    {
        foreach ($this->fields as $type) {
            if ($type instanceof Text) {
                $type->handleCustomAnalyzer($analysis);
            }

            if ($type instanceof FieldContainer) {
                $type->getProperties()->handleCustomAnalyzers($analysis);
            }
        }
    }

    public function handleNormalizers(AnalysisInterface $analysis): void
    {

        foreach ($this->fields as $type) {
            if ($type instanceof Keyword) {
                $type->handleNormalizer($analysis);
            }

            if ($type instanceof FieldContainer) {
                $type->getProperties()->handleNormalizers($analysis);
            }
        }
    }

    public static function create(
        array|stdClass $raw,
        Analyzer $defaultAnalyzer,
        array $analyzers,
        string $name,
        string $parentPath = '',
        array $normalizers = [],
    ): self {

        $fields = [];

        foreach ($raw as $fieldName => $value) {
            // A field named "0" is stored as int 0. The type constructors require a string.
            $fieldName = (string) $fieldName;
            $value = (array) $value;

            // Elasticsearch permits an implicit object, including one with just enabled or dynamic.
            $value['type'] ??= 'object';
            if (isset($value['meta'])) {
                $value['meta'] = (array) $value['meta'];
            }

            $numberClass = ($value['meta']['type'] ?? '') === 'boost' ? Boost::class : Number::class;

            $field = match (true) {
                in_array($value['type'], ['object', 'nested'], true) => (function () use ($fieldName, $value, $defaultAnalyzer, $analyzers, $parentPath, $normalizers): Object_|Nested {
                    $childPath = $parentPath === '' ? $fieldName : $parentPath.'.'.$fieldName;
                    $children = self::create($value['properties'] ?? [], $defaultAnalyzer, $analyzers, $fieldName, $childPath, $normalizers);
                    $class = $value['type'] === 'nested' ? Nested::class : Object_::class;

                    return new $class($fieldName, $children, array_diff_key($value, ['type' => true, 'properties' => true]));
                })(),
                $value['type'] === 'completion' => Text::fromRaw([$fieldName => $value]),
                in_array(
                    $value['type'],
                    ['search_as_you_type', 'text']
                ) => match ($value['meta']['type'] ?? '') {
                    'long_text' => LongText::fromRaw([$fieldName => $value]),
                    'short_text' => ShortText::fromRaw([$fieldName => $value]),
                    'h_t_m_l' => HTML::fromRaw([$fieldName => $value]),
                    'category' => Category::fromRaw([$fieldName => $value]),
                    'title' => Title::fromRaw([$fieldName => $value]),
                    'email' => Email::fromRaw([$fieldName => $value]),
                    'address' => Address::fromRaw([$fieldName => $value]),
                    'tags' => Tags::fromRaw([$fieldName => $value]),
                    'path' => Path::fromRaw([$fieldName => $value]),
                    'image' => Image::fromRaw([$fieldName => $value]),
                    'name' => Name::fromRaw([$fieldName => $value]),
                    'searchable_number' => SearchableNumber::fromRaw([$fieldName => $value]),
                    default => Text::fromRaw([$fieldName => $value]),
                },
                $value['type'] === 'keyword' && ($value['meta']['type'] ?? '') === 'case_sensitive_keyword' => new CaseSensitiveKeyword($fieldName),
                $value['type'] === 'keyword' && ($value['meta']['type'] ?? '') === 'identifier' => new Id($fieldName),
                $value['type'] === 'keyword' => new Keyword($fieldName),
                $value['type'] === 'integer' => (new $numberClass($fieldName))->integer(),
                $value['type'] === 'long' => (new $numberClass($fieldName))->long(),
                $value['type'] === 'float' => (new $numberClass($fieldName))->float(),
                $value['type'] === 'double' => (new $numberClass($fieldName))->double(),
                $value['type'] === 'scaled_float' && ($value['meta']['type'] ?? '') === 'price' => new Price($fieldName),
                $value['type'] === 'scaled_float' => (new $numberClass($fieldName))->scaledFloat($value['scaling_factor'] ?? 100),
                $value['type'] === 'boolean' => new Boolean($fieldName),
                $value['type'] === 'geo_point' => new GeoPoint($fieldName),
                $value['type'] === 'date' => (function () use ($fieldName, $value): Date|DateTime {
                    $formats = explode('||', $value['format'] ?? 'strict_date_optional_time||epoch_millis');

                    return ($value['meta']['type'] ?? '') === 'date_time'
                        ? new DateTime($fieldName, $formats)
                        : new Date($fieldName, $formats);
                })(),
                $value['type'] === 'flat_object',
                $value['type'] === 'flattened' && ($value['meta']['type'] ?? '') === 'flat_object'
                    && array_diff_key($value, ['type' => true, 'meta' => true]) === [] => new FlatObject($fieldName),
                $value['type'] === 'dense_vector' => DenseVector::fromMapping($fieldName, $value),
                $value['type'] === 'knn_vector' => KnnVector::fromMapping($fieldName, $value),
                $value['type'] === 'integer_range' => (new Range($fieldName))->integer(),
                $value['type'] === 'float_range' => (new Range($fieldName))->float(),
                $value['type'] === 'long_range' => (new Range($fieldName))->long(),
                $value['type'] === 'double_range' => (new Range($fieldName))->double(),
                $value['type'] === 'date_range' => (new Range($fieldName, $value['format'] ?? null))->date(),
                $value['type'] === 'ip_range' => (new Range($fieldName))->ip(),
                default => new Field($fieldName, $value['type'], $value),
            };

            if ($field instanceof Keyword) {
                $field->restoreScalarMapping($value, $normalizers);
            } elseif ($field instanceof Number || $field instanceof CaseSensitiveKeyword
                || $field instanceof Boolean || $field instanceof GeoPoint || $field instanceof Price
                || $field instanceof Date || $field instanceof DateTime || $field instanceof Range) {
                $field->restoreScalarMapping($value);
            } elseif (isset($value['meta']) && ! $field instanceof Text) {
                $field->meta(array_diff_key($value['meta'], ['type' => true]));
            }

            if (isset($value['copy_to']) && ! $field instanceof Text) {
                $field->copyTo($value['copy_to']);
            }

            if ($field instanceof Text && ! isset($value['analyzer']) && ($value['type'] ?? '') !== 'completion') {
                $value['analyzer'] = 'default';
                if (isset($analyzers['default_search'])) {
                    $value['search_analyzer'] ??= 'default_search';
                }
            }

            if ($field instanceof Text && isset($value['analyzer'])) {
                $analyzerName = $value['analyzer'];

                $analyzer = $analyzers[$analyzerName] ?? match ($analyzerName) {
                    'simple' => new SimpleAnalyzer,
                    'standard' => new Standard,
                    'default' => $defaultAnalyzer,
                    default => new BuiltInAnalyzer($analyzerName, ['type' => $analyzerName])
                };

                $field->withAnalyzer($analyzer, $value['search_analyzer'] ?? null);
            }

            $fields[$fieldName] = $field;
        }

        $props = new Properties($name, $fields);
        if ($parentPath !== '') {
            $props->setFieldPaths($parentPath);
        }

        return $props;
    }

    public function toRaw(?SearchEngine $driver = null): array
    {
        return (new Collection($this->fields))
            ->mapWithKeys(function (ContractsType $value) use ($driver): array {
                if ($value instanceof BaseVector && $driver instanceof SearchEngine) {
                    return $driver->vectorField($value)->toRaw();
                }

                if ($value instanceof DenseVector && $driver instanceof SearchEngine) {
                    $nativeEngine = $value instanceof KnnVector ? SearchEngineType::OpenSearch : SearchEngineType::Elasticsearch;
                    if ($driver->engine() === $nativeEngine) {
                        return $value->toRaw();
                    }

                    $vector = new BaseVector(
                        name: $value->name,
                        dims: $value->dims(),
                        index: $value->isIndexed(),
                        similarity: $value->similarity(),
                        indexType: $value->indexType(),
                        m: $value->m(),
                        efConstruction: $value->efConstruction(),
                        autoNormalizeVector: ! $value instanceof KnnVector,
                    );

                    $vector->meta($value->getMeta());
                    $vector->setFieldPath($value->fullPath(), $value->nestedPath());

                    return $driver->vectorField($vector)->toRaw();
                }

                if (($value instanceof Nested || $value instanceof Object_ || $value instanceof FlatObject) && $driver instanceof SearchEngine) {
                    return $value->toRaw($driver);
                }

                return $value->toRaw();
            })
            ->toArray();
    }

    public function get(string $fieldName): ?Type
    {
        $type = $this->relativeField($fieldName);
        if ($type instanceof Type || $this->path === '' || ! str_starts_with($fieldName, $this->path.'.')) {
            return $type;
        }

        return $this->relativeField(substr($fieldName, strlen($this->path) + 1));
    }

    private function relativeField(string $fieldName): ?Type
    {
        $key = path_key($this->fields, $fieldName);
        if ($key === null) {
            return null;
        }

        $type = $this->fields[$key];
        if ($key === $fieldName) {
            return $type;
        }

        if ($type instanceof FieldContainer) {
            return $type->getProperties()->relativeField(substr($fieldName, strlen($key) + 1));
        }

        return null;
    }

    public function isRoot(): bool
    {
        return $this->name === self::ROOT_NAME;
    }

    public function fullPath(): string
    {
        return $this->path;
    }

    public function setFieldPaths(string $containerPath, ?string $nestedPath = null): void
    {
        $this->setFieldPath($containerPath, $nestedPath);
        foreach ($this->fields as $field) {
            $this->assignFieldPath($field);
        }
    }

    private function assignFieldPath(Type $field): void
    {
        $path = $this->path === '' ? $field->name : $this->path.'.'.$field->name;
        $field->setFieldPath($path, $this->nestedPath());
    }

    public function fieldNames(bool $withParent = false): array
    {
        $collection = new Collection($this->fields);

        return $collection->map(function (ContractsType $type) use ($withParent) {

            if ($type instanceof FieldContainer) {
                return [
                    ...$withParent ? [$type->fullPath()] : [],
                    ...$type->getProperties()->fieldNames($withParent),
                ];
            }

            return $type->fullPath();
        })->flatten()
            ->toArray();
    }

    public function nestedSemanticFields(): Collection
    {
        return $this->fieldsOfType(Text::class)
            ->filter(fn (Text $field): bool => $field->isSemantic());
    }

    /**
     * Return all fields of a given type, recursively including nested properties.
     * Keys are the field's full dot-path.
     *
     * @template T of ContractsType
     *
     * @param  class-string<T>  $class
     * @return Collection<string, T>
     */
    public function fieldsOfType(string $class): Collection
    {
        $direct = (new Collection($this->fields))
            ->filter(fn (ContractsType $type): bool => $type instanceof $class)
            ->mapWithKeys(fn (ContractsType $field): array => [$field->fullPath() => $field]);

        $nested = $this->deepFields()
            ->mapWithKeys(fn (FieldContainer $field): array => $field->getProperties()->fieldsOfType($class)->toArray());

        return new Collection($direct->toArray() + $nested->toArray());
    }

    public function getProperties(): Properties
    {
        return $this;
    }

    public function hasFields(): bool
    {
        return $this->fields !== [];
    }
}
