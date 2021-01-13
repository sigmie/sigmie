<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs\Calls;

use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Search\Query;
use Sigmie\Http\JsonRequest;

trait Search
{
    use API;

    protected function searchAPICall(Query $query): ElasticsearchResponse
    {
        $uri = $query->uri();

        $esRequest = new JsonRequest('POST', $uri, $query->toArray());

        return $this->call($esRequest, ElasticsearchResponse::class);
    }
}
