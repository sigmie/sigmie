<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Clauses;

class Should extends Clause
{
    private $raw;

    public function match($field, $value)
    {
        $this->raw = ['match' => [$field => $value]];

        return $this->queryBuilder;
    }

    public function key()
    {
        return 'should';
    }

    public function raw()
    {
        return $this->raw;
    }
}
