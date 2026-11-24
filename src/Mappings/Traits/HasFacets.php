<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Traits;

use Sigmie\Enums\FacetLogic;
use Sigmie\Parse\ParseException;
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
        if (! preg_match('/\A([0-9]+)(?:,(asc|desc))?\z/', $params, $matches)
            || (int) $matches[1] < 1 || (int) $matches[1] > 2147483647) {
            throw new ParseException('Term facet parameters require a positive integer size and optional asc or desc order.');
        }

        $aggregation = $aggs->terms($this->name(), $field)->size((int) $matches[1]);

        if (isset($matches[2])) {
            $aggregation->order('_key', $matches[2]);
        }
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
