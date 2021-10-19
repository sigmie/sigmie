<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Sigmie\Base\Contracts\Queries;
use Sigmie\Base\Contracts\QueryClause as Query;
use Sigmie\Base\Search\Queries\Compound\Boolean;
use Sigmie\Base\Search\Queries\MatchAll;
use Sigmie\Base\Search\Queries\MatchNone;
use Sigmie\Base\Search\Queries\Term\Exists;
use Sigmie\Base\Search\Queries\Term\Fuzzy;
use Sigmie\Base\Search\Queries\Term\IDs;
use Sigmie\Base\Search\Queries\Term\Range;
use Sigmie\Base\Search\Queries\Term\Regex;
use Sigmie\Base\Search\Queries\Term\Term;
use Sigmie\Base\Search\Queries\Term\Terms;
use Sigmie\Base\Search\Queries\Term\Wildcard;
use Sigmie\Base\Search\Queries\Text\Match_;
use Sigmie\Base\Search\Queries\Text\MultiMatch;

class BooleanQueryBuilder implements Queries
{
    protected array $clauses = [];

    public function matchAll(): self
    {
        $this->clauses[] = new MatchAll;

        return $this;
    }

    public function matchNone(): self
    {
        $this->clauses[] = new MatchNone;

        return $this;
    }

    public function match(string $field, string $query): self
    {
        $this->clauses[] = new Match_($field, $query);

        return $this;
    }

    public function query(Query $query): self
    {
        return $this->search->query($query);
    }

    public function multiMatch(string $query, array $fields = []): self
    {
        $this->clauses[] = new MultiMatch($query, $fields);

        return $this;
    }

    public function exists(string $field): self
    {
        $this->clauses[] = new Exists($field);

        return $this;
    }

    public function ids(array $ids): self
    {
        $this->clauses[] = new IDs($ids);

        return $this;
    }

    public function fuzzy(string $field, string $value): self
    {
        $this->clauses[] = new Fuzzy($field, $value);

        return $this;
    }

    public function term(string $field, string|bool $value): self
    {
        $this->clauses[] = new Term($field, $value);

        return $this;
    }

    public function terms(string $field, array $values): self
    {
        $this->clauses[] = new Terms($field, $values);

        return $this;
    }

    public function regex(string $field, string $regex): self
    {
        $this->clauses[] = new Regex($field, $regex);

        return $this;
    }

    public function wildcard(string $field, string $value): self
    {
        $this->clauses[] = new Wildcard($field, $value);

        return $this;
    }

    public function range(
        string $field,
        null|float|int|string $min = null,
        null|float|int|string $max = null,
    ): self {
        $this->clauses[] = new Range($field, $min, $max);

        return $this;
    }

    public function bool(callable $callable): self
    {
        $query = new Boolean;

        $this->clauses[] = $query;

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
