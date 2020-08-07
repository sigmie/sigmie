<?php

declare(strict_types=1);

namespace Sigmie\Search\Indices;

use Sigmie\Search\BaseEntityCollection;

class IndexCollection extends BaseEntityCollection
{
    public function entityClassName(): string
    {
        return Index::class;
    }

    public function mapItems($value)
    {
        $className = $this->entityClassName();

        return new $className([$value]);
    }
}
