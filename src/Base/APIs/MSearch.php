<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Http\Requests\MSearch as RequestsMSearch;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Search\Contracts\MultiSearchable;
use Sigmie\Search\MultiSearchResponse;

trait MSearch
{
    use API;

    protected function msearchAPICall(array $body): ElasticsearchResponse
    {
        $uri = new Uri('/_msearch');

        $esRequest = new RequestsMSearch('POST', $uri, $body);

        return $this->elasticsearchCall($esRequest);
    }
}
