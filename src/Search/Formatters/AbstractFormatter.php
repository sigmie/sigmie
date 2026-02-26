<?php

namespace Sigmie\Search\Formatters;

use Sigmie\Search\Contracts\ResponseFormater;
use Sigmie\Search\SearchContext;

abstract class AbstractFormatter implements ResponseFormater
{
    protected array $queryResponseRaw = [];

    protected array $facetsResponseRaw = [];

    protected array $facets = [];

    protected array $errors;

    protected SearchContext $search;

    protected int $responseCode = 200;

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

    public function responseCode(int $code): static
    {
        $this->responseCode = $code;

        return $this;
    }

    public function code(): int
    {
        return $this->responseCode;
    }

    public function facetAggregations(): array
    {
        return $this->facetsResponseRaw['aggregations'] ?? [];
    }
}
