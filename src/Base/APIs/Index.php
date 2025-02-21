<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Index
{
    use API;

    public function indexAPICall(string $index, string $method, ?array $body = null): ElasticsearchResponse
    {
        $uri = new Uri("/{$index}");

        $esRequest = new ElasticsearchRequest($method, $uri, $body);

        return $this->elasticsearchCall($esRequest);
    }
}
