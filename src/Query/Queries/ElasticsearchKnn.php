<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

class ElasticsearchKnn extends NearestNeighbors
{
    public function toRaw(): array
    {
        return [
            "knn" => [
                "field" => $this->field,
                "query_vector" => $this->queryVector,
                "k" => $this->k,
                "filter" => $this->filter,
                "num_candidates" => $this->numCandidates,
                'boost' => $this->boost,
            ],
        ];
    }
}
