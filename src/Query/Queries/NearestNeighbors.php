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
        protected array|string $embeddings
    ) {}

    public function toRaw(): array
    {
        return match (Sigmie::$version) {
            Version::v7 => throw new \Exception('NearestNeighbors is not supported in Elasticsearch 7'),
            Version::v8 => [
                "knn" => [
                    "field" => $this->field,
                    "query_vector" => $this->embeddings,
                    "k" => 100,
                    "num_candidates" => 100
                    // "model" => "exact",
                    // "similarity" => "cosine",
                ],
                'boost' => $this->boost
            ]
        };
    }
}
