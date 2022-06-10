<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Http\Requests\MSearch as RequestsMSearch;
use Sigmie\Base\Http\Requests\Search as SearchRequest;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Http\NdJSONRequest;

trait MSearch
{
    use API;

    protected function msearchAPICall(array $body): SearchResponse
    {
        $uri = new Uri("/_msearch");

        $esRequest = new RequestsMSearch('POST', $uri, $body);

        return $this->httpCall($esRequest);
    }
}
