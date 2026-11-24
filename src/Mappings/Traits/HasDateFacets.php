<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Traits;

use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Term\Exists;

/**
 * A date facet is the covered range, like a Number facet: `count`, and the earliest and latest
 * date as `min`/`max` strings in the field's first format (null when no document matches).
 * Counts per period belong to analytics (`trend`, `date_histogram`), not to the facet.
 */
trait HasDateFacets
{
    use HasFacets;

    public function aggregation(Aggs $aggs, string $param): void
    {
        $aggs->filter($this->name().'_present', new Exists($this->name()));
        $aggs->stats($this->name(), $this->name())->format($this->formats[array_key_first($this->formats)]);
    }

    public function isFacetable(): bool
    {
        return true;
    }

    public function facets(array $aggregation): ?array
    {
        $stats = $aggregation[$this->name()] ?? [];

        return [
            'count' => $aggregation[$this->name().'_present']['doc_count'] ?? 0,
            'min' => $stats['min_as_string'] ?? null,
            'max' => $stats['max_as_string'] ?? null,
        ];
    }
}
