<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries;

use Sigmie\Base\Contracts\QueryClause as QueryClause;

abstract class Query implements QueryClause
{
    abstract public function toRaw(): array;
}
