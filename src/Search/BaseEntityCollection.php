<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Doctrine\Common\Collections\ArrayCollection;
use Sigmie\Contracts\EntityCollection;

abstract class BaseEntityCollection extends ArrayCollection implements EntityCollection
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
