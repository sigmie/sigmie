<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

use function Sigmie\Functions\keyed;

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
            'knn' => keyed($this->field, [
                'vector' => is_array($this->queryVector) ? array_values($this->queryVector) : $this->queryVector,
                'k' => $this->k,
                ...($this->filter === [] ? [] : ['filter' => $this->filter]),
                'boost' => $this->boost,
            ]),
        ];
    }
}
