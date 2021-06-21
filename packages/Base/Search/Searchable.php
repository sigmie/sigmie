<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Sigmie\Base\Contracts\API;

trait Searchable
{
    use API;

    public function search()
    {
        return new QueryBuilder($this);
    }
}
