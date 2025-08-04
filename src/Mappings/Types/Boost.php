<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;


class Boost extends Number
{
    public function __construct(
        string $name,
    ) {
        parent::__construct($name);

        $this->float();
    }

    public function isFacetable(): bool
    {
        return false;
    }

    public function queries(array|string $queryString): array
    {
        $queries = [];

        // It's unlikely to search in an input field
        // for a price.

        // Price type is better for range filters

        return $queries;
    }
}
