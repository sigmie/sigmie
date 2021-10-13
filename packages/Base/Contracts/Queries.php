<?php declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Contracts\QueryClause as Query;

interface Queries
{
    public function term(string $field, string $value);

    public function bool(callable $callable);

    public function range(string $field, null|float|int|string $min = null, null|float|int|string $max = null,);

    public function matchAll();

    public function query(Query $query);

    public function matchNone();

    public function match(string $field, string $query);

    public function multiMatch(array $fields, string $query);

    public function exists(string $field);

    public function ids(array $ids);

    public function fuzzy(string $field, string $value);

    public function terms(string $field, array $values);

    public function regex(string $field, string $regex);

    public function wildcard(string $field, string $value);
}
