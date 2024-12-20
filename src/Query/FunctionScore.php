<?php

namespace Sigmie\Query;

use Sigmie\Query\Queries\Query;

class FunctionScore
{
    public function __construct(
        public Query $query,
        public string $source,
        public string $boostMode = 'multiply',
    ) {}

    public function toRaw(): array
    {
        return [
            'function_score' => [
                'query' => $this->query->toRaw(),
                'script_score' => [
                    'script' => ['source' => $this->source],
                ],
                'boost_mode' => $this->boostMode,
            ],
        ];
    }
}
