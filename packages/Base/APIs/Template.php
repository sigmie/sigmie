<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Template
{
    use API;

    protected function templateAPICall(string $name, string $method, null|array $body = null): ElasticsearchResponse
    {
        $uri = new Uri("/_template/{$name}");

        $esRequest = new ElasticsearchRequest($method, $uri, $body);

        return $this->elasticsearchCall($esRequest);
    }
}
