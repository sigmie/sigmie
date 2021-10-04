<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

use ArrayAccess;
use Sigmie\Base\Mappings\Types\Text;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Arrayable;
use Sigmie\Support\Contracts\Collection as CollectionInterface;

class Properties implements Arrayable, ArrayAccess
{
    public function __construct(protected array $fields = [])
    {
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

    public function offsetExists($offset)
    {
        return isset($this->fields[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->fields[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->fields[$offset] = $value;
    }

    public function offsetUnset(mixed $offset)
    {
        unset($this->fields[$offset]);
    }


    public function toRaw(): array
    {
        $fields = new Collection($this->fields);
        $fields = $fields->mapToDictionary(function (PropertyType $value) {
            return $value->toRaw();
        })->toArray();

        return $fields;
    }
}
