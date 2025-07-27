<?php

declare(strict_types=1);

namespace Sigmie\Search\Contracts;

use Sigmie\Search\SearchContext;

interface ResponseFormater
{
    public function json(array $raw): static;
    
    public function format(): array;

    public function context(SearchContext $context): static;
}
