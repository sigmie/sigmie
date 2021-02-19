<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Clauses;

use Sigmie\Base\Search\QueryBuilder;

abstract class Clause
{
    protected QueryBuilder $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
}
