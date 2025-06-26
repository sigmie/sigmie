<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

use Sigmie\Query\Queries\Query;
use Sigmie\Enums\ElasticsearchVersion as Version;
use Sigmie\Sigmie;

class NearestNeighbors extends Query
{
    public function __construct(
        protected string $field,
        protected array|string $queryVector,
        protected int $k = 300,
        protected int $numCandidates = 1000,
    ) {}

    public function k(int $k): self
    {
        $this->k = $k;

        return $this;
    }

    public function toRaw(): array
    {
        return match (Sigmie::$version) {
            Version::v7 => throw new \Exception('NearestNeighbors is not supported in Elasticsearch 7'),
            Version::v8 => [
                "knn" => [
                    "field" => $this->field,
                    "query_vector" => $this->queryVector,
                    "k" => $this->k, // Needs to be same as size in search
                    // "num_candidates" => $this->numCandidates,
                    "num_candidates" => 10000 ,
                    'boost' => $this->boost,
                ],
            ]
        };
    }
}
