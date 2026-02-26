<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Contracts;

use Sigmie\Query\Aggs;

interface Facetable
{
    /**
     * Check if this field is facetable
     */
    public function isFacetable(): bool;

    /**
     * Get facets from aggregation results
     */
    public function facets(array $aggregation): ?array;

    /**
     * Add aggregation to the query
     */
    public function aggregation(Aggs $aggs, string $params): void;

    /**
     * Check if facet is conjunctive
     */
    public function isFacetConjunctive(): bool;

    /**
     * Check if facet is disjunctive
     */
    public function isFacetDisjunctive(): bool;

    /**
     * Check if facet is searchable
     */
    public function isFacetSearchable(): bool;

    /**
     * Set facet to conjunctive mode
     */
    public function facetConjunctive(): static;

    /**
     * Set facet to disjunctive mode
     */
    public function facetDisjunctive(): static;
}
