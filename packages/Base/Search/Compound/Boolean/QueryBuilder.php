<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Compound\Boolean;

use Closure;
use Sigmie\Base\APIs\Search as SearchAPI;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Index\AbstractIndex;
use Sigmie\Base\Search\Clauses\Boolean;
use Sigmie\Base\Search\Clauses\Filtered;
use Sigmie\Base\Search\Clauses\Match_;
use Sigmie\Base\Search\Clauses\Query as QueryClause;
use Sigmie\Base\Search\Queries\Term as QueriesTerm;
use Sigmie\Base\Search\Term\Term;
use Sigmie\Http\Contracts\JSONRequest;

class QueryBuilder
{
    private array $clauses = [];

    public function matchAll()
    {
        return;
    }

    public function match($filed, $value)
    {
        $this->queries[] = new Match_;

        return $this->query->match($filed, $value);
    }

    public function multiMatch()
    {
        return;
    }

    public function term($field, $value)
    {
        $this->clauses[] = (new Term())->term($field, $value);

        return $this;
    }

    public function range()
    {
        return;
    }

    public function bool(callable $callable)
    {
        $this->query = new Boolean;

        return $callable($this->query);
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
