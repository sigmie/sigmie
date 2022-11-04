<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Stats
{
    use API;

    protected function statsAPICall(string $index): ElasticsearchResponse
    {
        $uri = new Uri("/{$index}/_stats");

        $esRequest = new ElasticsearchRequest('GET', $uri);

        return $this->elasticsearchCall($esRequest);
    }
}
