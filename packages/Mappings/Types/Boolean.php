<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Query\Queries\Term\Term;

class Boolean extends Type
{
    protected string $type = 'boolean';

    public function queries(string $queryString): array
    {
        $queries = [];

        if (trim(strtolower($queryString)) === trim(strtolower($this->name))) {
            $queries[] = new Term($this->name, true);
        }

        return $queries;
    }
}
