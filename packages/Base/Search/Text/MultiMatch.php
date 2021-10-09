<?php

namespace Sigmie\Base\Search\Clauses;

trait MultiMatch
{
    protected array $multiMatch = [];

    public function multiMatch($query, $fields)
    {
        $this->multiMatch[] = ['multi_match' => [
            'query' => $query,
            'fields' => $fields
        ]];

        return $this;
    }
}
