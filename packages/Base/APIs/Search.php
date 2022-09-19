<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Requests\Search as SearchRequest;
use Sigmie\Base\Http\Responses\Search as SearchResponse;

trait Search
{
    use API;

    protected function searchAPICall(string $index, array $query): SearchResponse
    {
        $uri = new Uri("/{$index}/_search");

        $esRequest = new SearchRequest('POST', $uri, $query);

        return $this->httpCall($esRequest);
    }
}
