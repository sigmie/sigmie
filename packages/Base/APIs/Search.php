<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Http\Requests\Search as SearchRequest;
use Sigmie\Base\Http\Responses\Search as SearchResponse;

trait Search
{
    use API;

    protected function searchAPICall(string $index, array $query): SearchResponse
    {
        $esRequest = $this->searchRequest($index, $query);

        return $this->elasticsearchCall($esRequest);
    }

    protected function searchRequest(string $index, array $query): SearchRequest
    {
        $uri = new Uri("/{$index}/_search");

        return new SearchRequest('POST', $uri, $query);
    }
}
