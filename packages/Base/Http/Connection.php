<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\HttpConnection as ConnectionInterface;
use Sigmie\Http\Contracts\JSONClient as JSONClientInterface;

class Connection implements ConnectionInterface
{
    protected JSONClientInterface $http;

    public function __construct(JsonClientInterface $http)
    {
        $this->http = $http;
    }

    public function __invoke(ElasticsearchRequest $request, array $options = []): ElasticsearchResponse
    {
        $uri = $request->getUri();

        $request = $request->withUri($uri);

        $jsonResponse = $this->http->request($request);

        $response = $request->response($jsonResponse->psr());

        if ($request->getMethod() === 'HEAD' && $response->failed()) {
            return $response;
        }

        if ($response->failed()) {
            throw $response->exception($request);
        }

        return $response;
    }
}
