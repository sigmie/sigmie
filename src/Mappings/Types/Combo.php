<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Closure;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Mappings\NewSemanticField;
use Sigmie\Shared\Collection;

class Combo extends Text
{
    protected array $sourceFields = [];

    public function __construct(
        string $name,
        array $sourceFields
    ) {
        parent::__construct($name);

        $this->sourceFields = $sourceFields;
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
