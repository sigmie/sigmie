<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

use Sigmie\Query\Queries\Query;
use Sigmie\Enums\ElasticsearchVersion as Version;
use Sigmie\Enums\SearchEngine;
use Sigmie\Sigmie;

class NearestNeighbors extends Query
{
    public function __construct(
        protected string $field,
        protected array|string $queryVector,
        protected int $k = 300,
        protected int $numCandidates = 1000,
        protected array $filter = [],
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
        $res = match (Sigmie::$engine) {
            SearchEngine::OpenSearch => $this->toOpenSearchRaw(),
            SearchEngine::Elasticsearch => $this->toElasticsearchRaw(),
        };

        return $res;
    }

    protected function toElasticsearchRaw(): array
    {
        return [
            "knn" => [
                "field" => $this->field,
                "query_vector" => $this->queryVector,
                "k" => $this->k,
                'filter' => $this->filter,
                "num_candidates" => $this->k * 10,
                'boost' => $this->boost,
            ],
        ];
    }

    protected function toOpenSearchRaw(): array
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
