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

    public function typeName(): string
    {
        return 'boost';
    }

    public function isFacetable(): bool
    {
        return false;
    }

    public function scriptScoreSource(): string
    {
        return 'doc.containsKey(params.field) && doc[params.field].size() > 0 ? doc[params.field].value : 1';
    }

    public function scriptScoreBoostMode(): string
    {
        return 'multiply';
    }

    public function queries(array|string $queryString): array
    {
        // It's unlikely to search in an input field
        // for a price.

        // Price type is better for range filters

        return [];
    }
}
