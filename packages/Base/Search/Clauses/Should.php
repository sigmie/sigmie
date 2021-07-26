<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Clauses;

use Sigmie\Base\Search\QueryBuilder;

class Should extends Clause
{
    private $raw;

    public function match($field, $value): QueryBuilder
    {
        $this->raw = ['match' => [$field => $value]];

        return $this->queryBuilder;
    }

    public function key(): string
    {
        return 'should';
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
