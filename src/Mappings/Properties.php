<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use ArrayAccess;
use Exception;
use Sigmie\English\Filter\Lowercase;
use Sigmie\English\Filter\Stemmer;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Analysis\SimpleAnalyzer;
use Sigmie\Index\Analysis\TokenFilter\Shingle;
use Sigmie\Index\Analysis\TokenFilter\Trim;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Shared\Collection;
use Throwable;

class Properties extends Type implements ArrayAccess
{
    public function __construct(string $name = 'mappings', protected array $fields = [])
    {
        $this->type = ElasticsearchMappingType::PROPERTIES->value;

        if ($name === 'mappings') {
            $this->fields['boost'] = (new Number('boost'))->float();
            $this->fields['autocomplete'] = (new Text('autocomplete'))->completion();
        }

        parent::__construct($name);
    }

    public function autocomplete(Analyzer $analyzer)
    {
        $this->fields['autocomplete'] = (new Text('autocomplete'))->completion($analyzer);
    }

    public function queries(string $queryString): array
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

        return $collection->filter(fn(Type $type) => $type instanceof Text);
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

    public static function create(array $raw, DefaultAnalyzer $defaultAnalyzer, array $analyzers, string $name): self
    {
        $fields = [];

        foreach ($raw as $fieldName => $value) {

            $field = match (true) {
                // This is an object type
                isset($value['properties']) && !isset($value['type']) => (new Object_($fieldName))->properties(
                    self::create($value['properties'], $defaultAnalyzer, $analyzers, (string) $fieldName)
                ),
                isset($value['properties']) && $value['type'] === 'nested' => (new Nested($fieldName))->properties(
                    self::create($value['properties'], $defaultAnalyzer, $analyzers, (string) $fieldName)
                ),
                in_array(
                    $value['type'],
                    ['search_as_you_type', 'text', 'completion']
                ) => Text::fromRaw([$fieldName => $value]),
                $value['type'] === 'keyword' => (new Keyword($fieldName)),
                $value['type'] === 'integer' => (new Number($fieldName))->integer(),
                $value['type'] === 'long' => (new Number($fieldName))->long(),
                $value['type'] === 'float' => (new Number($fieldName))->float(),
                $value['type'] === 'scaled_float' => (new Number($fieldName))->scaledFloat(),
                $value['type'] === 'boolean' => new Boolean($fieldName),
                $value['type'] === 'geo_point' => new GeoPoint($fieldName),
                $value['type'] === 'date' => new Date($fieldName),
                $value['type'] === 'object' => new Object_($fieldName),
                default => throw new Exception('Field ' . $value['type'] . ' couldn\'t be mapped')
            };

            if ($field instanceof Text && !isset($value['analyzer'])) {
                $value['analyzer'] = 'default';
            }

            if ($field instanceof Text && isset($value['analyzer'])) {
                $analyzerName = $value['analyzer'];

                $analyzer = match ($analyzerName) {
                    'simple' => new SimpleAnalyzer(),
                    'default' => $defaultAnalyzer,
                    default => $analyzers[$analyzerName]
                };

                $field->withAnalyzer($analyzer);
            }

            $fields[$fieldName] = $field;
        }

        return new Properties($name, $fields);
    }

    public function toRaw(): array
    {
        $fields = (new Collection($this->fields))
            ->mapToDictionary(fn(Type $value) => $value->toRaw())
            ->toArray();

        if ($this->name === 'mappings') {
            return $fields;
        } else {
            return [$this->name() => ['properties' => $fields]];
        }
    }

    public function getNestedField(string $fieldName)
    {
        $fields = explode('.', $fieldName);

        $firstField = array_shift($fields);

        if (!isset($this->fields[$firstField])) {
            return null;
        }

        $type = $this->fields[$firstField];

        if (empty($fields)) {
            return $type;
        }

        if ($type instanceof Nested || $type instanceof Object_) {
            $childName = implode('.', $fields);
            return $type->properties->getNestedField($childName);
        }

        return null;
    }

    public function propertiesParent(string $parentPath, string $parentType)
    {
        foreach ($this->fields as $field) {
            $field->parent($parentPath, $parentType);
        }
    }
}
