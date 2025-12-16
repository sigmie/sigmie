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
use Sigmie\Mappings\Contracts\FieldContainer;
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

class Properties extends Type implements ArrayAccess, FieldContainer
{
    public const ROOT_NAME = 'mappings';

    protected array $fields = [];

    public readonly Boost $boostField;

    public function __construct(string $name = self::ROOT_NAME, array $fields = [])
    {
        parent::__construct($name);

        $this->type = ElasticsearchMappingType::PROPERTIES->value;

        $boostField = array_values(array_filter($fields, fn (Type $field): bool => $field instanceof Boost))[0] ?? null;

        // Boost field can only as a top level prop
        if ($name === self::ROOT_NAME && $boostField) {
            $this->boostField = $boostField;
        }

        // Set paths for all fields
        $containerPath = $this->isRoot() ? '' : $this->name;
        $this->fields = array_map(function (Type $field) use ($containerPath): Type {
            $field->setPath($containerPath !== '' && $containerPath !== '0' ? sprintf('%s.%s', $containerPath, $field->name) : $field->name);

            return $field;
        }, $fields);
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

        return $collection->filter(fn (Type $type): bool => $type instanceof FieldContainer);
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
                    $childPath = $parentPath !== '' && $parentPath !== '0' ? sprintf('%s.%s', $parentPath, $fieldName) : $fieldName;
                    $props = self::create(
                        $value['properties'],
                        $defaultAnalyzer,
                        $analyzers,
                        (string) $fieldName,
                        $childPath
                    );

                    $obj = new Object_($fieldName, $props);
                    // Re-set paths - constructor uses wrong path before Object_'s path is set
                    $props->setFieldPaths($childPath);

                    return $obj;
                })(),
                isset($value['properties']) && $value['type'] === 'nested' => (function () use (
                    $fieldName,
                    $value,
                    $defaultAnalyzer,
                    $analyzers,
                    $parentPath
                ): Nested {
                    $childPath = $parentPath !== '' && $parentPath !== '0' ? sprintf('%s.%s', $parentPath, $fieldName) : $fieldName;
                    $props = self::create(
                        $value['properties'],
                        $defaultAnalyzer,
                        $analyzers,
                        (string) $fieldName,
                        $childPath
                    );

                    $nested = new Nested($fieldName, $props);
                    // Re-set paths - constructor uses wrong path before Nested's path is set
                    $props->setFieldPaths($childPath);

                    return $nested;
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
        // $parentPath already contains the full path to this container
        $fullPath = $parentPath ?: $name;
        $props->setPath($fullPath);

        // Update children paths with correct full container path
        $containerPath = $name === self::ROOT_NAME ? '' : $fullPath;
        $props->setFieldPaths($containerPath);

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

        if ($type instanceof FieldContainer) {
            $childName = implode('.', $fields);

            return $type->getProperties()->get($childName);
        }

        return null;
    }

    public function isRoot(): bool
    {
        return $this->name === self::ROOT_NAME;
    }

    public function fullPath(): string
    {
        if ($this->isRoot()) {
            return '';
        }

        return parent::fullPath();
    }

    public function setFieldPaths(string $containerPath): void
    {
        foreach ($this->fields as $field) {
            $field->setPath($containerPath !== '' && $containerPath !== '0' ? sprintf('%s.%s', $containerPath, $field->name) : $field->name);
        }
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

    private function semanticFields(): Collection
    {
        $textFields = $this->textFields()
            ->filter(fn (Text $field): bool => $field->isSemantic())
            ->mapWithKeys(fn (Text $field) => [$field->fullPath() => $field]);

        $imageFields = $this->imageFields()
            ->filter(fn (Image $field): bool => $field->isSemantic())
            ->mapWithKeys(fn (Image $field) => [$field->fullPath() => $field]);

        return $textFields->merge($imageFields);
    }

    public function nestedSemanticFields(): Collection
    {
        $semanticFields = $this->semanticFields();

        $nestedFields = $this->deepFields()
            ->mapWithKeys(fn (FieldContainer $field): array => [
                ...$field->getProperties()->semanticFields()->toArray(),
                ...$field->getProperties()->nestedSemanticFields()->toArray(),
            ]);

        $res = [
            ...$semanticFields->toArray(),
            ...$nestedFields->toArray(),
        ];

        return new Collection($res);
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
