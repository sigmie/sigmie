<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Traits\HasFacets;
use Sigmie\Query\Aggs;

class CaseSensitiveKeyword extends Type
{
    use HasFacets;

    protected string $type = 'keyword';

    public function queries(array|string $queryString): array
    {
        return $this->queryStrings($queryString, fn (string $queryString): array => [...$this->termQueries($this->name(), $queryString), ...$this->prefixQueries($this->name(), $queryString)]);
    }

    public function aggregation(Aggs $aggs, string $params): void
    {
        $this->termFacetAggregation($aggs, $this->name(), $params);
    }

    public function facets(array $aggregation): ?array
    {
        $originalBuckets = $aggregation[$this->name()]['buckets'] ?? [];

        return array_column($originalBuckets, 'doc_count', 'key');
    }

    public function isFacetable(): bool
    {
        return $this->hasDocValues();
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_string($value)) {
            return [false, sprintf('The field %s mapped as %s must be a string', $key, $this->typeName())];
        }

        return $this->validateKeywordTerms($key, $value);
    }
}
