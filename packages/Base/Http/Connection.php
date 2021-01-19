<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Exception;
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

    public function __invoke(JSONRequest $request, string $responseClass = ElasticsearchResponse::class): ElasticsearchResponse
    {
        $uri = $request->getUri();

        $request = $request->withUri($uri);

        $jsonResponse = $this->http->request($request);

        /** @var  ElasticsearchResponse */
        $response = new $responseClass($jsonResponse->psr());

        if ($response instanceof ElasticsearchResponse === false) {
            $interface = ElasticsearchResponse::class;

            throw new Exception("Class of {$responseClass} doesnt' implement {$interface}") ;
        }

        if ($response->failed()) {
            throw $response->exception($request);
        }

        return $response;
    }
}
