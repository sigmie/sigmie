<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

use ArrayAccess;
use Sigmie\Base\Mappings\Types\Text;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;

class Properties implements ArrayAccess
{
    public function __construct(protected array $fields = [])
    {
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

    public function toRaw(): array
    {
        return (new Collection($this->fields))->mapToDictionary(fn (PropertyType $value) => $value->toRaw())
            ->toArray();
    }
}
