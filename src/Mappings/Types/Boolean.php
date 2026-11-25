<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Query\Queries\Term\Term;

use function Sigmie\Functions\fold_text;

class Boolean extends Type
{
    protected string $type = 'boolean';

    public function queryStringQueries(array|string $queryString): array
    {
        return $this->queryStrings($queryString, fn (string $query): array => parent::queryStringQueries($query));
    }

    public function queries(array|string $queryString): array
    {
        // The array result stays lowercased strings. The string path returns
        // a Term. boolean_queries_accept_an_array locks the strings, so this
        // branch must not call queryStrings().
        if (is_array($queryString)) {
            return array_map(
                fn (string $query): string => mb_strtolower($query, 'UTF-8'),
                $queryString,
            );
        }

        $queries = [];

        if (fold_text($queryString) === fold_text($this->name)) {
            $queries[] = new Term($this->name(), true);
        }

        return $queries;
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_bool($value) && ! $this->ignoresMalformedScalar($value)) {
            return [false, sprintf('The field %s mapped as %s must be a boolean', $key, $this->typeName())];
        }

        return [true, ''];
    }
}
