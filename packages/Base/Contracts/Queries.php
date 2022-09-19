<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Contracts\QueryClause as Query;

interface Queries
{
    public function term(string $field, string $value);

    public function bool(callable $callable, float $boost);

    public function range(string $field, array $values, float $boost);

    public function matchAll(float $boost);

    public function query(Query $query);

    public function matchNone(float $boost);

    public function match(string $field, string $query, float $boost);

    public function multiMatch(string $query, array $fields, float $boost);

    public function exists(string $field, float $boost);

    public function ids(array $ids, float $boost);

    public function fuzzy(string $field, string $value, float $boost);

    public function terms(string $field, array $values, float $boost);

    public function regex(string $field, string $regex, float $boost);

    public function wildcard(string $field, string $value, float $boost);
}
