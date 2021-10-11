<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries;

use Sigmie\Base\Contracts\ToRaw;
use Sigmie\Base\Search\QueryBuilder;

abstract class QueryClause implements ToRaw
{
    abstract public function toRaw(): array;
}
