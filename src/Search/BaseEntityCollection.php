<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Illuminate\Support\Collection;
use Sigmie\Contracts\EntityCollection;

abstract class BaseEntityCollection extends Collection implements EntityCollection
{
    public function __construct(array $data)
    {
        $items = array_map([$this, 'mapItems'], $data);

        parent::__construct($items);
    }

    public function mapItems($value)
    {
        $className = $this->entityClassName();

        return new $className($value);
    }

    abstract public function entityClassName(): string;
}
