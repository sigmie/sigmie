<?php

namespace Sigmie\Query;

use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\Queries\Query;

class FunctionScore implements QueryClause
{
    protected float $boost = 1;

    public function __construct(
        public Query $query,
        public string $source,
        public string $boostMode = 'multiply',
    ) {}

    public function boost(float $boost = 1): QueryClause
    {
        return $this;
    }

    public function toRaw(): array
    {
        return [
            'function_score' => [
                'query' => $this->query->toRaw(),
                'boost' => $this->boost,
                'script_score' => [
                    'script' => ['source' => $this->source],
                ],
                'boost_mode' => $this->boostMode,
            ],
        ];
    }
}
