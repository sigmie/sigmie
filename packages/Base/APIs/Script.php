<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Script
{
    use API;

    protected function scriptAPICall(string $method, string $id, ?array $body = null): ElasticsearchResponse
    {
        $uri = new Uri("/_scripts/{$id}");

        $esRequest = new ElasticsearchRequest($method, $uri, $body);

        return $this->elasticsearchCall($esRequest);
    }
}
