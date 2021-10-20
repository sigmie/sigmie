<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Contracts\QueryClause as Query;

interface Queries
{
    public function term(string $field, string|bool $value);

    public function bool(callable $callable);

    public function range(string $field, array $values);

    public function matchAll();

    public function query(Query $query);

    public function matchNone();

    public function match(string $field, string $query);

    public function multiMatch(string $query, array $fields = []);

    public function exists(string $field);

    public function ids(array $ids);

    public function fuzzy(string $field, string $value);

    public function terms(string $field, array $values);

    public function regex(string $field, string $regex);

    public function wildcard(string $field, string $value);
}
