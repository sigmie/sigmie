<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Term\Prefix;

class Id extends Keyword
{
    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Term($this->name, $queryString);

        return $queries;
    }
}
