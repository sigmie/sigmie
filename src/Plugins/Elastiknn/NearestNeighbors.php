<?php

declare(strict_types=1);

namespace Sigmie\Plugins\Elastiknn;

use Sigmie\Query\Queries\NearestNeighbors as QueryNearestNeighbors;
use Sigmie\Shared\Contracts\ToRaw;

class NearestNeighbors extends QueryNearestNeighbors implements ToRaw
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
