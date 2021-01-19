<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Clauses;

class Query extends Clause
{
    private $raw;

    public function match($field, $value)
    {
        $this->raw = ['match' => [$field => $value]];

        return $this->queryBuilder;
    }

    public function matchAll()
    {
        $this->type = 'match_all';

        return $this->queryBuilder;
    }

    public function raw()
    {
        return $this->raw;
    }

    public function key()
    {
        return 'query';
    }
}
