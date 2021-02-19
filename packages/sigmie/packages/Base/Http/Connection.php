<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Exception;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\HttpConnection as ConnectionInterface;
use Sigmie\Http\Contracts\JSONClient as JSONClientInterface;
use Sigmie\Http\Contracts\JSONRequest;

class Connection implements ConnectionInterface
{
    private JSONClientInterface $http;

    public function __construct(JsonClientInterface $http)
    {
        $this->http = $http;
    }

    public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
    {
        $uri = $request->getUri();

        $request = $request->withUri($uri);

        $jsonResponse = $this->http->request($request);

        $response = $request->response($jsonResponse->psr());

        if ($response->failed()) {
            throw $response->exception($request);
        }

        return $response;
    }
}
