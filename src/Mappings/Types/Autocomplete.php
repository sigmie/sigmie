<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Index\NewAnalyzer;

class Autocomplete extends Text
{
    public function __construct(
        string $name,
    ) {
        parent::__construct($name);

        $this->completion();

        $this->newAnalyzer(function (NewAnalyzer $newAnalyzer) use ($name) {
            $newAnalyzer->tokenizeOnWordBoundaries();
            $newAnalyzer->asciiFolding();
            $newAnalyzer->unique();
            $newAnalyzer->trim();
            $newAnalyzer->decimalDigit();
            $newAnalyzer->shingle(2, 3);
        });
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
