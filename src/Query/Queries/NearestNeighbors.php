<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Query\Queries\Query;

class NearestNeighbors extends Query
{
    protected ?SearchEngine $driver = null;

    public function __construct(
        protected string $field,
        protected array|string $queryVector,
        protected int $k = 300,
        protected int $numCandidates = 1000,
        protected array $filter = [],
        protected float $boost = 1.0,
    ) {}

    /**
     * This ia passed at query time
     */
    public function k(int $k): self
    {
        $this->k = $k;

        return $this;
    }

    /**
     * This is passed at query time
     */
    public function filter(array $filter): self
    {
        $this->filter = $filter;

        return $this;
    }

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
