<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

class Combo extends Text
{
    public function __construct(
        string $name,
        protected array $sourceFields
    ) {
        parent::__construct($name);
    }

    public function sourceFields(): array
    {
        return $this->sourceFields;
    }

    public function queries(array|string $queryString): array
    {
        return [];
    }

    public function isFacetable(): bool
    {
        return false;
    }

    public function isFilterable(): bool
    {
        return false;
    }

    public function toRaw(): array
    {
        return [];
    }
}
