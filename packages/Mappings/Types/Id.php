<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

class Id extends CaseSensitiveKeyword
{
    public function queries(string $queryString): array
    {
        $queries = [];

        return $queries;
    }
}
