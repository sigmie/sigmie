<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;

class CaseSensitiveKeyword extends Type
{
    protected string $type = 'keyword';

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Term($this->name, $queryString);

        $queries[] = new Prefix($this->name, $queryString);

        return $queries;
    }
}
