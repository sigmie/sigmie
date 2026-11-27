<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

use Sigmie\Query\Contracts\QueryClause;
use stdClass;

abstract class Query implements QueryClause
{
    protected float $boost = 1;

    abstract public function toRaw(): array|stdClass;

    protected function wrapWithBoost(array|stdClass $query): array
    {
        return ['bool' => ['must' => $query, 'boost' => $this->boost]];
    }

    public function boost(float $boost = 1): self
    {
        $this->boost = $boost;

        return $this;
    }
}
