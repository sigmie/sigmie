<?php

namespace Sigmie\Base\Search\Term;

use Sigmie\Base\Search\QueryBuilder;

class Term
{
    protected array $term = [];

    public function term($field, $value)
    {
        $this->term = ['term' => [
            $field => [
                'value' => $value
            ]
        ]];

        return $this;
    }

    public function toRaw()
    {
        return $this->term;
    }
}
