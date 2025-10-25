<?php

namespace Sigmie\Query;

use Sigmie\Query\Contracts\QueryClause;

class FunctionScore implements QueryClause
{
    protected float $boost = 1;

    public function __construct(
        public QueryClause $query,
        public string $source,
        public string $boostMode = 'multiply',
        public array $params = [],
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
                    'script' => [
                        'source' => $this->source,
                        'params' => (object) $this->params,
                    ],
                ],
                'boost_mode' => $this->boostMode,
            ],
        ];
    }
}
