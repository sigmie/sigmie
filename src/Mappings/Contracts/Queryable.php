<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Contracts;

use Closure;

interface Queryable
{
    /**
     * Get queries for this field
     */
    public function queries(array|string $queryString): array;

    /**
     * Get query string queries
     */
    public function queryStringQueries(array|string $queryString): array;

    /**
     * Set custom queries via callback
     */
    public function withQueries(Closure $closure): static;

    /**
     * Get queries from callback
     */
    public function queriesFromCallback(string $queryString): array;
}
