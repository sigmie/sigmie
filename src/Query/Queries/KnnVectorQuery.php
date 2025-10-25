<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

abstract class KnnVectorQuery extends Query
{
    public function __construct(
        protected string $field,
        protected array|string $queryVector,
        protected int $k = 300,
        protected int $numCandidates = 1000,
        protected array $filter = [],
        protected float $boost = 1.0,
    ) {}
}
