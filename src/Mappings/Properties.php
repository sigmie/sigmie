<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use ArrayAccess;
use Exception;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Analysis\SimpleAnalyzer;
use Sigmie\Index\Analysis\Standard;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Mappings\Contracts\Type as ContractsType;
use Sigmie\Mappings\Types\Autocomplete;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Boost;
use Sigmie\Mappings\Types\Combo;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Mappings\Types\FlatObject;
use Sigmie\Plugins\Elastiknn\DenseFloatVector;
use Sigmie\Shared\Collection;

class Properties extends Type implements ArrayAccess
{
    protected array $fields = [];

    public readonly Boost $boostField;

    public readonly Autocomplete $autocompleteField;

    public function __construct(string $name = 'mappings', array $fields = [])
    {
        $this->type = ElasticsearchMappingType::PROPERTIES->value;

        $boostField = array_values(array_filter($fields, fn(Type $field) => $field instanceof Boost))[0] ?? null;
        $autocompleteField = array_values(array_filter($fields, fn(Type $field) => $field instanceof Autocomplete))[0] ?? null;

        // Boost field can only as a top level prop 
        if ($name === 'mappings' && $boostField) {
            $this->boostField = $boostField;
        }

        if ($name === 'mappings' && $autocompleteField) {
            $this->autocompleteField = $autocompleteField;
        }

        $this->fields = array_map(function (Type $field) use ($name) {

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

        return $collection->filter(fn(ContractsType $type) => $type instanceof Text);
    }

    public function deepFields(): Collection
    {
        $collection = new Collection($this->fields);

        return $collection->filter(fn(Type $type) => $type instanceof Object_ || $type instanceof Nested);
    }

    public function embeddingsFields(): Collection
    {
        return $this->textFields()->filter(fn(Text $text) => $text->isSemantic());
    }

    public function completionFields(): Collection
    {
        $collection = new Collection($this->fields);

        return $collection->filter(fn(Type $type) => $type instanceof Text)
            ->filter(fn(Text $text) => $text->type() === 'completion');
    }

    public function toArray(): array
    {
        return $this->fields;
    }

    public function handleCustomAnalyzers(AnalysisInterface $analysis)
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

    public function handleNormalizers(AnalysisInterface $analysis)
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
                isset($value['properties']) && ! isset($value['type']) => (function () use ($fieldName, $value, $defaultAnalyzer, $analyzers, $parentPath) {

                    $props = self::create(
                        $value['properties'],
                        $defaultAnalyzer,
                        $analyzers,
                        (string) $fieldName,
                        $fieldName
                    );
                    $props->parent($parentPath, self::class);

                    $object = new Object_(
                        $fieldName,
                        $props,
                    );

                    return $object;
                })(),
                isset($value['properties']) && $value['type'] === 'nested' => (function () use (
                    $fieldName,
                    $value,
                    $defaultAnalyzer,
                    $analyzers,
                    $parentPath
                ) {
                    $props = self::create(
                        $value['properties'],
                        $defaultAnalyzer,
                        $analyzers,
                        (string) $fieldName,
                    );
                    $props->parent($parentPath, self::class);

                    $nested = new Nested($fieldName, $props);

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
                $value['type'] === 'elastiknn_dense_float_vector' => new DenseFloatVector($fieldName, $value['elastiknn']['dims']),
                $value['type'] === 'dense_vector' => new DenseVector($fieldName, $value['dims']),
                $value['type'] === 'knn_vector' => new DenseFloatVector($fieldName, $value['dimension']),
                $value['type'] === 'integer_range' => (new Range($fieldName))->integer(),
                $value['type'] === 'float_range' => (new Range($fieldName))->float(),
                $value['type'] === 'long_range' => (new Range($fieldName))->long(),
                $value['type'] === 'double_range' => (new Range($fieldName))->double(),
                $value['type'] === 'date_range' => (new Range($fieldName))->date(),
                $value['type'] === 'ip_range' => (new Range($fieldName))->ip(),
                default => throw new Exception('Field ' . $value['type'] . ' couldn\'t be mapped')
            };

            if ($field instanceof Text && ! isset($value['analyzer'])) {
                $value['analyzer'] = 'default';
            }

            if ($field instanceof Text && isset($value['analyzer'])) {
                $analyzerName = $value['analyzer'];

                $analyzer = match ($analyzerName) {
                    'simple' => new SimpleAnalyzer(),
                    'standard' => new Standard(),
                    'default' => $defaultAnalyzer,
                    default => $analyzers[$analyzerName]
                };

                $field->withAnalyzer($analyzer);
            }

            $fields[$fieldName] = $field;
        }

        $props = new Properties($name, $fields);
        $props->parent("{$parentPath}.{$name}", self::class);

        return $props;
    }

    public function toRaw(): array
    {
        $fields = (new Collection($this->fields))
            ->filter(fn(ContractsType $value) => !($value instanceof Combo))
            ->mapToDictionary(fn(ContractsType $value) => $value->toRaw())
            ->toArray();

        // if (in_array($this->name, ['mappings', 'embeddings'])) {
        return $fields;
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

        if (empty($fields)) {
            return $type;
        }

        if ($type instanceof Nested || $type instanceof Object_) {
            $childName = implode('.', $fields);

            return $type->properties->get($childName);
        }

        return null;
    }

    public function propertiesParent(string $parentPath, string $parentType)
    {
        foreach ($this->fields as $field) {
            $field->parent($parentPath, $parentType);
        }
    }

    public function fieldNames(bool $withParent = false)
    {
        $collection = new Collection($this->fields);

        return $collection->map(function (ContractsType $type) use ($withParent) {

            if ($type instanceof Object_) {
                return [
                    ...$withParent ? [$type->fullPath] : [],
                    ...$type->properties->fieldNames($withParent)
                ];
            }

            if ($type instanceof Nested) {
                return [
                    ...$withParent ? [$type->fullPath] : [],
                    ...$type->properties->fieldNames($withParent)
                ];
            }

            return $type->fullPath;
        })->flatten()
            ->toArray();
    }

    private function semanticFields()
    {
        return $this->textFields()
            ->filter(fn(Text $field) => $field->isSemantic())
            ->mapWithKeys(fn(Text $field) => [$field->name() => $field]);
    }

    public function nestedSemanticFields()
    {
        $textFields = $this->semanticFields();

        $nestedFields = $this->deepFields()
            ->mapWithKeys(function (Object_|Nested $field) {

                $result = [
                    ...$field->properties->semanticFields()->toArray(),
                    ...$field->properties->nestedSemanticFields()->toArray()
                ];

                return $result;
            });

        $res = [
            ...$textFields->toArray(),
            ...$nestedFields->toArray()
        ];

        return new Collection($res);
    }
}
