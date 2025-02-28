<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Elastiknn;

use Sigmie\Query\Queries\Query;

class NearestNeighbors extends Query
{
    public function __construct(
        protected string $field,
        protected array|string $embeddings
    ) {}

    public function toRaw(): array
    {
        return [
            "elastiknn_nearest_neighbors" => [
                "field" => $this->field,
                "vec" => [
                    "values" => $this->embeddings
                ],
                "model" => "exact",
                "similarity" => "cosine",
            ]
        ];
    }
}
