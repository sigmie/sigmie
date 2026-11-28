<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Traits;

use Sigmie\Enums\FacetLogic;
use Sigmie\Parse\ParseException;
use Sigmie\Query\Aggregations\Bucket\Bucket;
use Sigmie\Query\Aggregations\Bucket\ReverseNested;
use Sigmie\Query\Aggs;

trait HasFacets
{
    protected FacetLogic $facetLogic = FacetLogic::Conjunctive;

    public function aggregation(Aggs $aggs, string $params): void
    {
        // Override in implementing classes
    }

    protected function termFacetAggregation(Aggs $aggs, string $field, string $params): void
    {
        if (! preg_match('/\A(\d+)(?:,(asc|desc))?\z/', $params, $matches)
            || (int) $matches[1] < 1 || (int) $matches[1] > 2147483647) {
            throw new ParseException('Term facet parameters require a positive integer size and optional asc or desc order.');
        }

        $aggregation = $aggs->terms($this->name(), $field)->size((int) $matches[1]);
        $this->countDocuments($aggregation);

        if (isset($matches[2])) {
            $aggregation->order('_key', $matches[2]);

            return;
        }

        if ($this->nestedPath() !== null) {
            $aggregation->orderBy(['_documents' => 'desc', '_key' => 'asc']);
        }
    }

    /** @return array<string, int> */
    protected function termFacets(array $aggregation): array
    {
        $counts = [];

        foreach ($aggregation[$this->name()]['buckets'] ?? [] as $bucket) {
            $counts[$bucket['key']] = $this->documentCount($bucket);
        }

        return $counts;
    }

    /**
     * A bucket of a nested field holds items, and one document holds many items.
     * The bucket counts its documents in a `_documents` reverse_nested aggregation.
     */
    protected function countDocuments(Bucket $bucket): void
    {
        if ($this->nestedPath() === null) {
            return;
        }

        $bucket->aggregate(fn (Aggs $aggs): ReverseNested => $aggs->reverseNested('_documents'));
    }

    protected function documentCount(array $bucket): int
    {
        return $this->nestedPath() === null
            ? $bucket['doc_count'] ?? 0
            : $bucket['_documents']['doc_count'] ?? 0;
    }

    public function isFacetConjunctive(): bool
    {
        return $this->facetLogic === FacetLogic::Conjunctive;
    }

    public function isFacetDisjunctive(): bool
    {
        return $this->facetLogic === FacetLogic::Disjunctive;
    }

    public function isFacetable(): bool
    {
        return false;
    }

    public function facets(array $aggregation): ?array
    {
        return null;
    }

    public function isFacetSearchable(): bool
    {
        return $this->facetLogic === FacetLogic::Searchable;
    }

    public function facetConjunctive(): static
    {
        $this->facetLogic = FacetLogic::Conjunctive;

        return $this;
    }

    public function facetDisjunctive(): static
    {
        $this->facetLogic = FacetLogic::Disjunctive;

        return $this;
    }
}
