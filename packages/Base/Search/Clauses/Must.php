<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Clauses;

class Must extends Clause
{
    private $raw;

    public function term($field, $value)
    {
        $this->raw = ['term' => [$field => $value]];

        return $this->queryBuilder;
    }

    public function raw()
    {
        return $this->raw;
    }

    public function key()
    {
        return 'must';
    }
}
