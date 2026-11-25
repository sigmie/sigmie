<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

class ElasticsearchKnn extends Query
{
    public function __construct(
        protected string $field,
        protected array|string $queryVector,
        protected int $k,
        protected int $numCandidates,
        protected array $filter,
        protected float $boost = 1.0,
    ) {}

    public function toRaw(): array
    {
        return [
            'knn' => [
                'field' => $this->field,
                'query_vector' => is_array($this->queryVector) ? array_values($this->queryVector) : $this->queryVector,
                'k' => $this->k,
                'filter' => is_int(array_key_first($this->filter)) ? array_values($this->filter) : $this->filter,
                'num_candidates' => $this->numCandidates,
                'boost' => $this->boost,
            ],
        ];
    }
}
