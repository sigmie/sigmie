<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Traits;

use Sigmie\Enums\FacetLogic;
use Sigmie\Query\Aggs;

trait HasFacets
{
    protected FacetLogic $facetLogic = FacetLogic::Conjunctive;

    public function aggregation(Aggs $aggs, string $params): void
    {
        // Override in implementing classes
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
