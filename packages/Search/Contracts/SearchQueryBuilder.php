<?php

declare(strict_types=1);

namespace Sigmie\Search\Contracts;

use Sigmie\Mappings\Properties;

interface SearchQueryBuilder extends SearchBuilder
{
    public function properties(Properties $properties): static;

    public function filter(string $filter): static;

    public function sort(string $sort): static;
}
