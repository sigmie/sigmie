<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Ingest
{
    use API;

    protected function ingestAPICall(string $name, string $method, ?array $body = null, string $action = ''): ElasticsearchResponse
    {
        $uri = new Uri('/_ingest/pipeline/'.rawurlencode($name).$action);

        $esRequest = new ElasticsearchRequest($method, $uri, $body);

        $response = $this->elasticsearchCall($esRequest);

        if ($esRequest->getMethod() === 'PUT' && $response->json('acknowledged') === false) {
            throw new ElasticsearchException($response->json(), $response->code());
        }

        return $response;
    }
}
