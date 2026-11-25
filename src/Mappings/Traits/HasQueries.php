<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Traits;

use Closure;

trait HasQueries
{
    public bool $hasQueriesCallback = false;

    public Closure $queriesClosure;

    public function queries(array|string $queryString): array
    {
        return [];
    }

    public function queryStringQueries(array|string $queryString): array
    {
        if ($this->hasQueriesCallback && is_array($queryString)) {
            $queries = [];

            foreach ($queryString as $query) {
                foreach ($this->queriesFromCallback($query) as $built) {
                    $queries[] = $built;
                }
            }

            return $queries;
        }

        if ($this->hasQueriesCallback) {
            return $this->queriesFromCallback($queryString);
        }

        return $this->queries($queryString);
    }

    public function queriesFromCallback(string $queryString): array
    {
        return ($this->queriesClosure)($queryString);
    }

    public function withQueries(Closure $closure): static
    {
        $this->hasQueriesCallback = true;
        $this->queriesClosure = $closure;

        return $this;
    }
}
