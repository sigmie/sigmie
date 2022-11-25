<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Text\Match_;

class Title extends Text
{
    public function __construct(
        string $name,
    ) {
        parent::__construct($name, raw: 'keyword');

        $this->unstructuredText()->indexPrefixes();
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Match_($this->name, $queryString);
        $queries[] = new Prefix($this->name, $queryString);

        return $queries;
    }
}
