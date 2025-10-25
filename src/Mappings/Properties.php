<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use ArrayAccess;
use Exception;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Analysis\SimpleAnalyzer;
use Sigmie\Index\Analysis\Standard;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Mappings\Contracts\Type as ContractsType;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Boost;
use Sigmie\Mappings\Types\Combo;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\FlatObject;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\Image;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Shared\Collection;

class Properties extends Type implements ArrayAccess
{
    protected array $fields = [];

    public readonly Boost $boostField;

    public function __construct(string $name = 'mappings', array $fields = [])
    {
        $this->type = ElasticsearchMappingType::PROPERTIES->value;

        $boostField = array_values(array_filter($fields, fn (Type $field): bool => $field instanceof Boost))[0] ?? null;

        // Boost field can only as a top level prop
        if ($name === 'mappings' && $boostField) {
            $this->boostField = $boostField;
        }

        $this->fields = array_map(function (Type $field) use ($name): Type {

            $name = $name !== 'mappings' ? $name : '';

            $field->parent($name, self::class);

            return $field;
        }, $fields);

        parent::__construct($name);
    }

    public function queries(array|string $queryString): array
    {
        return [];
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
        $this->fields[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->fields[$offset]);
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

        return $collection->filter(fn (Type $type): bool => $type instanceof Object_ || $type instanceof Nested);
    }

    public function embeddingsFields(): Collection
    {
        $textFields = $this->textFields()->filter(fn (Text $text): bool => $text->isSemantic());
        $imageFields = $this->imageFields()->filter(fn (Image $image): bool => $image->isSemantic());

        return $textFields->merge($imageFields);
    }

    public function completionFields(): Collection
    {
        $collection = new Collection($this->fields);

        return $collection->filter(fn (Type $type): bool => $type instanceof Text)
            ->filter(fn (Text $text): bool => $text->type() === 'completion');
    }

    public function toArray(): array
    {
        return $this->fields;
    }

    public function handleCustomAnalyzers(AnalysisInterface $analysis): void
    {
        foreach ($this->fields as $type) {
            if ($type instanceof Text) {
                $type->handleCustomAnalyzer($analysis);
            }

            if ($type instanceof Object_) {
                $type->properties->handleCustomAnalyzers($analysis);
            }

            if ($type instanceof Nested) {
                $type->properties->handleCustomAnalyzers($analysis);
            }
        }
    }

    public function handleNormalizers(AnalysisInterface $analysis): void
    {

        foreach ($this->fields as $type) {
            if ($type instanceof Keyword) {
                $type->handleNormalizer($analysis);
            }

            if ($type instanceof Object_) {
                $type->properties->handleNormalizers($analysis);
            }

            if ($type instanceof Nested) {
                $type->properties->handleNormalizers($analysis);
            }
        }
    }

    public static function create(
        array $raw,
        DefaultAnalyzer $defaultAnalyzer,
        array $analyzers,
        string $name,
        string $parentPath = ''
    ): self {

        $fields = [];

        foreach ($raw as $fieldName => $value) {

            $field = match (true) {
                // This is an object type
                isset($value['properties']) && ! isset($value['type']) => (function () use ($fieldName, $value, $defaultAnalyzer, $analyzers, $parentPath): Object_ {

                    $props = self::create(
                        $value['properties'],
                        $defaultAnalyzer,
                        $analyzers,
                        (string) $fieldName,
                        $fieldName
                    );
                    $props->parent($parentPath, self::class);

                    return new Object_(
                        $fieldName,
                        $props,
                    );
                })(),
                isset($value['properties']) && $value['type'] === 'nested' => (function () use (
                    $fieldName,
                    $value,
                    $defaultAnalyzer,
                    $analyzers,
                    $parentPath
                ): Nested {
                    $props = self::create(
                        $value['properties'],
                        $defaultAnalyzer,
                        $analyzers,
                        (string) $fieldName,
                    );
                    $props->parent($parentPath, self::class);

                    return new Nested($fieldName, $props);
                })(),
                in_array(
                    $value['type'],
                    ['search_as_you_type', 'text', 'completion']
                ) => Text::fromRaw([$fieldName => $value]),
                $value['type'] === 'keyword' => (new Keyword($fieldName)),
                $value['type'] === 'integer' => (new Number($fieldName))->integer(),
                $value['type'] === 'long' => (new Number($fieldName))->long(),
                $value['type'] === 'float' => (new Number($fieldName))->float(),
                $value['type'] === 'double' => (new Number($fieldName))->double(),
                $value['type'] === 'scaled_float' => (new Number($fieldName))->scaledFloat(),
                $value['type'] === 'boolean' => new Boolean($fieldName),
                $value['type'] === 'geo_point' => new GeoPoint($fieldName),
                $value['type'] === 'date' => new Date($fieldName),
                $value['type'] === 'object' => new Object_($fieldName),
                $value['type'] === 'flat_object' => new FlatObject($fieldName),
                $value['type'] === 'dense_vector' => (fn (): DenseVector => new DenseVector(
                    name: $fieldName,
                    dims: $value['dims'] ?? 384,
                    index: $value['index'] ?? true,
                    similarity: isset($value['similarity'])
                        ? VectorSimilarity::from($value['similarity'])
                        : VectorSimilarity::Cosine,
                    indexType: $value['index_options']['type'] ?? 'hnsw',
                    m: $value['index_options']['m'] ?? 64,
                    efConstruction: $value['index_options']['ef_construction'] ?? 300,
                ))(),
                $value['type'] === 'knn_vector' => (function () use ($fieldName, $value): DenseVector {
                    // Map OpenSearch space_type to VectorSimilarity enum
                    $spaceType = $value['method']['space_type'] ?? 'cosinesimil';
                    $similarity = match ($spaceType) {
                        'cosinesimil' => VectorSimilarity::Cosine,
                        'l2' => VectorSimilarity::Euclidean,
                        'innerproduct' => VectorSimilarity::DotProduct,
                        default => VectorSimilarity::Cosine,
                    };

                    return new DenseVector(
                        name: $fieldName,
                        dims: $value['dimension'] ?? 384,
                        index: true, // OpenSearch knn_vector is always indexed
                        similarity: $similarity,
                        indexType: $value['method']['name'] ?? 'hnsw',
                        m: $value['method']['parameters']['m'] ?? 64,
                        efConstruction: $value['method']['parameters']['ef_construction'] ?? 300,
                    );
                })(),
                $value['type'] === 'integer_range' => (new Range($fieldName))->integer(),
                $value['type'] === 'float_range' => (new Range($fieldName))->float(),
                $value['type'] === 'long_range' => (new Range($fieldName))->long(),
                $value['type'] === 'double_range' => (new Range($fieldName))->double(),
                $value['type'] === 'date_range' => (new Range($fieldName))->date(),
                $value['type'] === 'ip_range' => (new Range($fieldName))->ip(),
                default => throw new Exception('Field '.$value['type']." couldn't be mapped")
            };

            if ($field instanceof Text && ! isset($value['analyzer'])) {
                $value['analyzer'] = 'default';
            }

            if ($field instanceof Text && isset($value['analyzer'])) {
                $analyzerName = $value['analyzer'];

                $analyzer = match ($analyzerName) {
                    'simple' => new SimpleAnalyzer,
                    'standard' => new Standard,
                    'default' => $defaultAnalyzer,
                    default => $analyzers[$analyzerName]
                };

                $field->withAnalyzer($analyzer);
            }

            $fields[$fieldName] = $field;
        }

        $props = new Properties($name, $fields);
        $props->parent(sprintf('%s.%s', $parentPath, $name), self::class);

        return $props;
    }

    public function toRaw(): array
    {
        // if (in_array($this->name, ['mappings', '_embeddings'])) {
        return (new Collection($this->fields))
            ->filter(fn (ContractsType $value): bool => ! ($value instanceof Combo))
            ->mapToDictionary(fn (ContractsType $value): array => $value->toRaw())
            ->toArray();
        // } else {
        //     return [$this->name() => ['properties' => $fields]];
        // }
    }

    public function get(string $fieldName)
    {
        $fields = explode('.', $fieldName);

        $firstField = array_shift($fields);

        if (! isset($this->fields[$firstField])) {
            return null;
        }

        $type = $this->fields[$firstField];

        if ($fields === []) {
            return $type;
        }

        if ($type instanceof Nested || $type instanceof Object_) {
            $childName = implode('.', $fields);

            return $type->properties->get($childName);
        }

        return null;
    }

    public function propertiesParent(string $parentPath, string $parentType): void
    {
        foreach ($this->fields as $field) {
            $field->parent($parentPath, $parentType);
        }
    }

    public function fieldNames(bool $withParent = false): array
    {
        $collection = new Collection($this->fields);

        return $collection->map(function (ContractsType $type) use ($withParent) {

            if ($type instanceof Object_) {
                return [
                    ...$withParent ? [$type->fullPath] : [],
                    ...$type->properties->fieldNames($withParent),
                ];
            }

            if ($type instanceof Nested) {
                return [
                    ...$withParent ? [$type->fullPath] : [],
                    ...$type->properties->fieldNames($withParent),
                ];
            }

            return $type->fullPath;
        })->flatten()
            ->toArray();
    }

    private function semanticFields(): Collection
    {
        $textFields = $this->textFields()
            ->filter(fn (Text $field): bool => $field->isSemantic())
            ->mapWithKeys(fn (Text $field) => [$field->fullPath => $field]);

        $imageFields = $this->imageFields()
            ->filter(fn (Image $field): bool => $field->isSemantic())
            ->mapWithKeys(fn (Image $field) => [$field->fullPath => $field]);

        return $textFields->merge($imageFields);
    }

    public function nestedSemanticFields(): Collection
    {
        $semanticFields = $this->semanticFields();

        $nestedFields = $this->deepFields()
            ->mapWithKeys(fn (Object_|Nested $field): array => [
                ...$field->properties->semanticFields()->toArray(),
                ...$field->properties->nestedSemanticFields()->toArray(),
            ]);

        $res = [
            ...$semanticFields->toArray(),
            ...$nestedFields->toArray(),
        ];

        return new Collection($res);
    }
}
