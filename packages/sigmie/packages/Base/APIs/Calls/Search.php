<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs\Calls;

use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Search\Query;
use Sigmie\Http\JSONRequest;

trait Search
{
    use API;

    protected function searchAPICall(Query $query): ElasticsearchResponse
    {
        $uri = $query->uri();

        $esRequest = new ElasticsearchRequest('POST', $uri, $query->toArray());

        return $this->httpCall($esRequest);
    }
}
