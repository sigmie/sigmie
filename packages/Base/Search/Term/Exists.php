<?php

namespace Sigmie\Base\Search\Clauses;

trait Exists
{
    protected array $terms = [];

    public function term($field, $value)
    {
        $this->terms[] = ['term' => [$field => $value]];

        return $this;
    }

    public function terms($field, array $value)
    {
        $this->terms[] = ['terms' => [$field => $value]];

        return $this;
    }
}
