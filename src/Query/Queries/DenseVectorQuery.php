<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

use Sigmie\Query\Queries\Query;

class DenseVectorQuery extends Query
{
    public function __construct(
        protected string $field,
        protected array|string $queryVector,
        protected int $k = 300,
        protected int $numCandidates = 1000,
        protected array $filter = [],
        protected float $boost = 1.0,
    ) {}

    public function toRaw(): array
    {
        return [
            'knn' => [
                'field' => $this->field,
                "query_vector" => $this->queryVector,
                "k" => $this->k,
                "filter" => $this->filter,
                "num_candidates" => $this->numCandidates,
                'boost' => $this->boost,
            ]
        ];
    }
}
