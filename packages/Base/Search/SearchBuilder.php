<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Closure;
use Sigmie\Base\APIs\Search as SearchAPI;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Index\AbstractIndex;
use Sigmie\Base\Search\Clauses\Boolean;
use Sigmie\Base\Search\Clauses\Filtered;
use Sigmie\Base\Search\Clauses\Query as QueryClause;
use Sigmie\Http\Contracts\JSONRequest;

class SearchBuilder
{
    public string $index;

    public int $from;

    public int $size;

    public array $sort;

    public array $fields;

    public $query;

    public function queryBuilder(): QueryBuilder
    {
        $this->query = new QueryBuilder($this);

        return $this->query;
    }

    public function toRaw()
    {
        return [
            'query' => $this->query->toRaw(),
        ];
    }
}
