<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\ElasticsearchConnection as ElasticsearchConnectionInterface;
use Sigmie\Http\Contracts\JSONClient as JSONClientInterface;
use Sigmie\Http\JSONClient;

class ElasticsearchConnection implements ElasticsearchConnectionInterface
{
    protected JSONClientInterface $http;

    public function __construct(JsonClientInterface $http)
    {
        $this->http = $http;
    }

    public function __invoke(ElasticsearchRequest $request,): ElasticsearchResponse
    {
        $uri = $request->getUri();

        $request = $request->withUri($uri);

        $jsonResponse = $this->http->request($request);

        $response = $request->response($jsonResponse->psr());

        if ($request->getMethod() === 'HEAD') {
            return $response;
        }

        if ($response->failed()) {
            ray($request);
            throw $response->exception($request);
        }

        return $response;
    }
}
