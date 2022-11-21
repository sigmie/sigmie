<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Term\Prefix;

class Keyword extends Type
{
    public function toRaw(): array
    {
        return [$this->name => [
            'type' => 'keyword',
        ]];
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Term($this->name, $queryString);

        $queries[] = new Prefix($this->name, $queryString);

        return $queries;
    }
}
