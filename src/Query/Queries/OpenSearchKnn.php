<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

class OpenSearchKnn extends NearestNeighbors
{
    public function toRaw(): array
    {
        return [
            "knn" => [
                $this->field => [
                    "vector" => $this->queryVector,
                    "k" => $this->k,
                    "filter" => $this->filter,
                    'boost' => $this->boost,
                ],
            ],
        ];
    }
}
