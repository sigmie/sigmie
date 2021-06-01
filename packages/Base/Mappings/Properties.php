<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

use ArrayAccess;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Arrayable;

class Properties implements ArrayAccess, Arrayable
{
    public function __construct(protected array $fields = [])
    {
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

    public function offsetSet($offset, $value)
    {
        $this->fields[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->fields[$offset]);
    }


    public function toRaw(): array
    {
        $fields = new Collection($this->fields);
        $fields = $fields->mapToDictionary(function ($value) {
            return [$value->name() => $value->raw()];
        })->toArray();

        return $fields;
    }
}
