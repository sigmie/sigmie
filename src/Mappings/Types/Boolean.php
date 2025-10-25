<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Query\Queries\Term\Term;

class Boolean extends Type
{
    protected string $type = 'boolean';

    public function queries(array|string $queryString): array
    {
        $queries = [];

        if (trim(strtolower($queryString)) === trim(strtolower($this->name))) {
            $queries[] = new Term($this->name, true);
        }

        return $queries;
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_bool($value)) {
            return [false, sprintf('The field %s mapped as %s must be a boolean', $key, $this->typeName())];
        }

        return [true, ''];
    }
}
