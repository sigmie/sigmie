<?php

declare(strict_types=1);

namespace Sigmie\Query;

use Sigmie\Query\Contracts\Queries;
use Sigmie\Query\Contracts\QueryClause as Query;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\MatchNone;
use Sigmie\Query\Queries\Raw;
use Sigmie\Query\Queries\Term\Exists;
use Sigmie\Query\Queries\Term\Fuzzy;
use Sigmie\Query\Queries\Term\IDs;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Query\Queries\Term\Regex;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Term\Terms;
use Sigmie\Query\Queries\Term\Wildcard;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MultiMatch;

class BooleanQueryBuilder implements Queries
{
    protected array $clauses = [];

    public function matchAll(float|int $boost = 1): self
    {
        $clause =  new MatchAll();

        $this->clauses[] = $clause->boost($boost);

        return $this;
    }

    public function matchNone(float $boost = 1): self
    {
        $clause = new MatchNone();

        $this->clauses[] = $clause->boost($boost);

        return $this;
    }

    public function match(string $field, string $query, float $boost = 1): self
    {
        $clause = new Match_($field, $query);

        $this->clauses[] = $clause->boost($boost);

        return $this;
    }

    public function raw(string $raw): self
    {
        $clause = new Raw($raw);

        $this->clauses[] = $clause;

        return $this;
    }

    public function query(Query $query): self
    {
        $this->clauses[] = $query;

        return $this;
    }

    public function multiMatch(string $query, array $fields, float $boost = 1): self
    {
        $clause = new MultiMatch($query, $fields);

        $this->clauses[] = $clause->boost($boost);

        return $this;
    }

    public function exists(string $field, float $boost = 1): self
    {
        $clause = new Exists($field);

        $this->clauses[] = $clause->boost($boost);

        return $this;
    }

    public function ids(array $ids, float $boost = 1): self
    {
        $clause = new IDs($ids);

        $this->clauses[] = $clause->boost($boost);

        return $this;
    }

    public function fuzzy(string $field, string $value, float $boost = 1): self
    {
        $clause = new Fuzzy($field, $value);

        $this->clauses[] = $clause->boost($boost);

        return $this;
    }

    public function term(string $field, int|string|bool $value, float $boost = 1): self
    {
        $clause =  new Term($field, $value);

        $this->clauses[] = $clause->boost($boost);

        return $this;
    }

    public function terms(string $field, array $values, float $boost = 1): self
    {
        $clause = new Terms($field, $values);

        $this->clauses[] = $clause->boost($boost);

        return $this;
    }

    public function regex(string $field, string $regex, float $boost = 1): self
    {
        $clause = new Regex($field, $regex);

        $this->clauses[] = $clause->boost($boost);

        return $this;
    }

    public function wildcard(string $field, string $value, float $boost = 1): self
    {
        $clause = new Wildcard($field, $value);

        $this->clauses[] = $clause->boost($boost);

        return $this;
    }

    public function range(
        string $field,
        array $values = [],
        float $boost = 1
    ): self {
        $clause =  new Range($field, $values);

        $this->clauses[] = $clause->boost($boost);

        return $this;
    }

    public function bool(callable $callable, float $boost = 1): self
    {
        $query = new Boolean();

        $this->clauses[] = $query->boost($boost);

        $callable($query);

        return $this;
    }

    public function toRaw()
    {
        $res = [];

        foreach ($this->clauses as $claus) {
            $res[] = $claus->toRaw();
        }

        return $res;
    }
}
