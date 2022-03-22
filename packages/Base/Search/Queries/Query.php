<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries;

use Sigmie\Base\Contracts\QueryClause as QueryClause;

abstract class Query implements QueryClause
{
    protected float $boost = 1;

    abstract public function toRaw(): array;

    public function boost(float $boost = 1): self
    {
        $this->boost = $boost;

        return $this;
    }
}
