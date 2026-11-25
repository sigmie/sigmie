<?php

declare(strict_types=1);

namespace Sigmie\Query;

use Sigmie\Query\Contracts\QueryClause;
use stdClass;

class FunctionScore implements QueryClause
{
    protected float $boost = 1;

    public function __construct(
        public QueryClause $query,
        public ?string $source = null,
        public string $boostMode = 'multiply',
        public array $params = [],
        public ?float $minScore = null,
    ) {}

    public function boost(float $boost = 1): QueryClause
    {
        $this->boost = $boost;

        return $this;
    }

    private function scriptParams(): array|stdClass
    {
        foreach (array_keys($this->params) as $key) {
            // PHP hides object properties whose names start with a null byte.
            if (str_starts_with((string) $key, "\0")) {
                return $this->params;
            }
        }

        return (object) $this->params;
    }

    public function toRaw(): array
    {
        return [
            'function_score' => [
                'query' => $this->query->toRaw(),
                'boost' => $this->boost,
                ...($this->source === null ? [] : [
                    'script_score' => [
                        'script' => [
                            'source' => $this->source,
                            'params' => $this->scriptParams(),
                        ],
                    ],
                ]),
                'boost_mode' => $this->boostMode,
                ...($this->minScore === null ? [] : ['min_score' => $this->minScore]),
            ],
        ];
    }
}
