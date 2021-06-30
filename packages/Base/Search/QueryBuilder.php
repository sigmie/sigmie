<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Closure;
use Sigmie\Base\APIs\Search as SearchAPI;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Index\Index;
use Sigmie\Base\Search\Clauses\Boolean;
use Sigmie\Base\Search\Clauses\Filtered;
use Sigmie\Base\Search\Clauses\Query as QueryClause;
use Sigmie\Http\Contracts\JSONRequest;

class QueryBuilder
{
    use SearchAPI;

    private array $values;

    private Closure $call;

    private Index $index;

    public function __construct(Index $index)
    {
        $this->index = $index;
    }

    public function filtered(): Filtered
    {
        return new Filtered($this);
    }

    public function query(): QueryClause
    {
        $query = new QueryClause($this);

        $this->values[] = $query;

        return $query;
    }

    public function bool(): Boolean
    {
        $query = new Boolean($this);

        $this->values[] = $query;

        return $query;
    }

    public function get(): mixed
    {
        $q = [];
        foreach ($this->values as $value) {
            $q[$value->key()] = $value->raw();
        }

        $query = new Query($q);
        $query->index($this->index);

        return $this->index->find($query);
    }

    protected function httpCall(JSONRequest $request): ElasticsearchResponse
    {
        return ($this->call)($request);
    }
}
