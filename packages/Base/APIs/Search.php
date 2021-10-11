<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Http\Requests\Search as SearchRequest;

trait Search
{
    use API;

    protected function searchAPICall(string $index, array $query): ElasticsearchResponse
    {
        $uri = new Uri("/{$index}/_search");

        $esRequest = new SearchRequest('POST', $uri, $query);

        return $this->httpCall($esRequest);
    }
}
