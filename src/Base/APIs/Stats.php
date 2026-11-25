<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Stats
{
    use API;

    protected function statsAPICall(string $index): ElasticsearchResponse
    {
        $uri = $this->indexUri($index, '_stats');

        $esRequest = new ElasticsearchRequest('GET', $uri);

        $response = $this->elasticsearchCall($esRequest);
        $this->assertNoFailedShards($response);

        return $response;
    }
}
