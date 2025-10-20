<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

use Sigmie\Base\Contracts\SearchEngineDriver;
use Sigmie\Query\Queries\Query;

abstract class NearestNeighbors extends Query
{
    protected ?SearchEngineDriver $driver = null;

    public function __construct(
        protected string $field,
        protected array|string $queryVector,
        protected int $k = 300,
        protected int $numCandidates = 1000,
        protected array $filter = [],
        protected float $boost = 1.0,
    ) {}

    /**
     * This ia passed at query time
     */
    public function k(int $k): self
    {
        $this->k = $k;

        return $this;
    }

    /**
     * This is passed at query time
     */
    public function filter(array $filter): self
    {
        $this->filter = $filter;

        return $this;
    }
}
