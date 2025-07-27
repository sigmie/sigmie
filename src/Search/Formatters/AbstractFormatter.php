<?php

namespace Sigmie\Search\Formatters;

use Sigmie\Search\Contracts\ResponseFormater;
use Sigmie\Search\Contracts\SearchBuilder;
use Sigmie\Search\SearchContext;

abstract class AbstractFormatter implements ResponseFormater
{
    protected array $raw = [];

    protected SearchContext $search;

    abstract public function format(): array;

    public function json(array $raw): static
    {
        $this->raw = $raw;
        return $this;
    }

    public function context(SearchContext $context): static
    {
        $this->search = $context;

        return $this;
    }
}
