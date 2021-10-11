<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Builders;

use Sigmie\Base\Search\BaseSearchBuilder;
use Sigmie\Base\Search\Queries\Compound\Boolean as BooleanQuery;

class Boolean extends Search
{
    public function __construct(callable $callable)
    {
        $this->query = new BooleanQuery;

        $callable($this->query);
    }
}
