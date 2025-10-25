<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

class OpenSearchKnn extends Query
{
    public function __construct(
        protected string $field,
        protected array|string $queryVector,
        protected int $k,
        protected array $filter,
        protected float $boost = 1.0,
    ) {}

    public function toRaw(): array
    {
        return [
            'knn' => [
                $this->field => [
                    'vector' => $this->queryVector,
                    'k' => $this->k,
                    'filter' => $this->filter,
                    'boost' => $this->boost,
                ],
            ],
        ];
    }
}
