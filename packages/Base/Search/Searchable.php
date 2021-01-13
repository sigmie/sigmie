<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\RequiresIndexAware;

trait Searchable
{
    use API, RequiresIndexAware;

    public function search()
    {
        return new QueryBuilder($this);
    }
}
