<?php

declare(strict_types=1);

namespace Sigmie\Query;

use Http\Promise\Promise;
use Sigmie\Base\APIs\Script as APIsScript;
use Sigmie\Base\APIs\Search as APIsSearch;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Contracts\QueryClause as Query;
use Sigmie\Query\Queries\MatchAll;

class Facets extends Search
{
    public function __construct(
        ElasticsearchConnection $elasticsearchConnection,
        Query $filters,
        Aggs $aggs,
    ) {
        parent::__construct($elasticsearchConnection);

        $this->query($filters);
        $this->aggs($aggs);
    }

    public function toRaw(): array
    {
        $result = [
            'query' => $this->query->toRaw(),
            'aggs' => $this->aggs->toRaw(),
        ];

        return $result;
    }
}
