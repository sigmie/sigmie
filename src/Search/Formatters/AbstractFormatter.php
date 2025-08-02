<?php

namespace Sigmie\Search\Formatters;

use Sigmie\Search\Contracts\ResponseFormater;
use Sigmie\Search\Contracts\SearchBuilder;
use Sigmie\Search\SearchContext;

abstract class AbstractFormatter implements ResponseFormater
{
    protected array $queryResponseRaw = [];

    protected array $facetsResponseRaw = [];

    protected array $facets = [];

    protected array $errors;

    protected SearchContext $search;

    abstract public function format(): array;

    public function queryResponseRaw(array $raw): static
    {
        $this->queryResponseRaw = $raw;

        return $this;
    }

    public function facetsResponseRaw(array $raw): static
    {
        $this->facetsResponseRaw = $raw;

        return $this;
    }

    public function errors(array $errors): static
    {
        $this->errors = $errors;

        return $this;
    }

    public function context(SearchContext $context): static
    {
        $this->search = $context;

        return $this;
    }

    public function facetAggregations(): array
    {
        return $this->facetsResponseRaw['aggregations'] ?? [];
    }
}
