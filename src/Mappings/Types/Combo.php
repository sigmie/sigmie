<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

class Combo extends Text
{
    public function __construct(
        string $name,
        protected array $sourceFields,
        // A restored combo replaces the field the index already maps at its name, so it replays that mapping.
        private ?Type $mappedField = null,
    ) {
        parent::__construct($name);
    }

    public function sourceFields(): array
    {
        return $this->sourceFields;
    }

    public function type(): string
    {
        return 'combo';
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
        return $this->mappedField?->toRaw() ?? [];
    }
}
