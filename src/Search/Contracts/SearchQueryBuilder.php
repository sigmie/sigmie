<?php

declare(strict_types=1);

namespace Sigmie\Search\Contracts;

use Sigmie\Mappings\Properties;
use Sigmie\Query\Contracts\QueryClause;

interface SearchQueryBuilder extends SearchBuilder
{
    public function properties(Properties $properties): static;

    public function filters(string $filters): static;

    public function filterQuery(QueryClause $query): static;

    public function sort(string $sort): static;
}
