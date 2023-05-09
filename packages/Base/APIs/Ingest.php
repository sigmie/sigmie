<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Ingest
{
    use API;

    protected function ingestAPICall(string $name, string $method, array $body = null): ElasticsearchResponse
    {
        $uri = new Uri("/_ingest/pipeline/{$name}");

        $esRequest = new ElasticsearchRequest($method, $uri, $body);

        return $this->elasticsearchCall($esRequest);
    }
}
