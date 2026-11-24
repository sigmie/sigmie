<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Script
{
    use API;

    protected function scriptAPICall(string $method, string $id, ?array $body = null): ElasticsearchResponse
    {
        $uri = new Uri('/_scripts/'.rawurlencode($id));

        $esRequest = new ElasticsearchRequest($method, $uri, $body);

        $response = $this->elasticsearchCall($esRequest);
        if (in_array($esRequest->getMethod(), ['PUT', 'POST'], true) && $response->json('acknowledged') === false) {
            throw new ElasticsearchException($response->json(), $response->code());
        }

        return $response;
    }
}
