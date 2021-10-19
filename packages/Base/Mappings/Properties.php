<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

use ArrayAccess;
use Sigmie\Base\Mappings\Types\Text;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Arrayable;
use Sigmie\Support\Contracts\Collection as CollectionInterface;

class Properties
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

    public function toRaw(): array
    {
        return (new Collection($this->fields))->mapToDictionary(fn (PropertyType $value) => $value->toRaw())
            ->toArray();
    }
}
