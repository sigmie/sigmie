<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Elastiknn;

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
            Version::v7 => [
                "elastiknn_nearest_neighbors" => [
                    "field" => $this->field,
                    "vec" => [
                        "values" => $this->embeddings
                    ],
                    "model" => "exact",
                    "similarity" => "cosine",
                ]
            ],
            Version::v8 => [
                "knn" => [
                    "field" => $this->field,
                    "query_vector" => $this->embeddings,
                    "k" => 100,
                    "num_candidates" => 100
                    // "model" => "exact",
                    // "similarity" => "cosine",
                ]
            ]
        };
    }
}
