<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

use ArrayAccess;
use Sigmie\Base\Contracts\FromRaw;
use Sigmie\Base\Mappings\Types\Text;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;
use Exception;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Analysis\SimpleAnalyzer;
use Sigmie\Base\Mappings\Types\Boolean;
use Sigmie\Base\Mappings\Types\Date;
use Sigmie\Base\Mappings\Types\Keyword;
use Sigmie\Base\Mappings\Types\Number;

class Properties extends PropertyType implements ArrayAccess
{
    public function __construct(string $name, protected array $fields = [])
    {
        $this->type = ElasticsearchMappingType::PROPERTIES->value;

        parent::__construct($name);
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

    public function textFields(): CollectionInterface
    {
        $collection = new Collection($this->fields);

        return $collection->filter(fn (PropertyType $type) => $type instanceof Text);
    }

    public function toArray(): array
    {
        return $this->fields;
    }

    public static function create(array $raw, DefaultAnalyzer $defaultAnalyzer, array $analyzers, string $name): self
    {
        $fields = [];

        foreach ($raw as $fieldName => $value) {
            $field = match (true) {
                isset($value['properties']) && !isset($value['properties']['type']) => self::create($value['properties'], $defaultAnalyzer, $analyzers, (string) $fieldName),
                in_array(
                    $value['type'],
                    ['search_as_you_type', 'text', 'completion']
                ) => Text::fromRaw([$fieldName => $value]),
                $value['type'] === 'keyword' => (new Keyword($fieldName)),
                $value['type'] === 'integer' => (new Number($fieldName))->integer(),
                $value['type'] === 'long' => (new Number($fieldName))->long(),
                $value['type'] === 'float' => (new Number($fieldName))->float(),
                $value['type'] === 'boolean' => new Boolean($fieldName),
                $value['type'] === 'date' => new Date($fieldName),
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
        return (new Collection($this->fields))
            ->mapToDictionary(fn (PropertyType $value) => $value->toRaw())
            ->toArray();
    }
}
