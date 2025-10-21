<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

use Sigmie\Query\Queries\Query;

class KnnVectorQuery extends Query
{
    public function __construct(
        protected string $field,
        protected array|string $queryVector,
        protected int $k = 300,
        protected array $filter = [],
        protected float $boost = 1.0,
    ) {}

    public function toRaw(): array
    {
        // OpenSearch uses a different kNN syntax
        // The kNN query goes in the "query" section, not as a top-level "knn"
        return [
            "knn" => [
                $this->field => [
                    "vector" => $this->queryVector,
                    "k" => $this->k,
                    "filter" => $this->filter,
                    // Not supported in OpeanSearhc
                    // "num_candidates" => 10000,
                    'boost' => $this->boost,
                ],
            ],
        ];
    }
}
