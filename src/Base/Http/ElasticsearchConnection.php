<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Http\Promise\Promise;
use Sigmie\Base\Contracts\ElasticsearchConnection as ElasticsearchConnectionInterface;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Http\Contracts\JSONClient as JSONClientInterface;
use Sigmie\Http\Contracts\JSONResponse;

class ElasticsearchConnection implements ElasticsearchConnectionInterface
{
    protected JSONClientInterface $http;

    protected SearchEngine $driver;

    public function __construct(
        JsonClientInterface $http,
        SearchEngine $driver = new Elasticsearch()
    ) {
        $this->http = $http;
        $this->driver = $driver;
    }

    public function driver(): SearchEngine
    {
        return $this->driver;
    }

    public function promise(ElasticsearchRequest $request): Promise
    {
        return $this->http->promise($request)
            ->then(function (JSONResponse $res) use ($request) {
                return $request->response($res->psr());
            });
    }

    public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
    {
        $uri = $request->getUri();

        $request = $request->withUri($uri);

        $jsonResponse = $this->http->request($request);

        $response = $request->response($jsonResponse->psr());

        if ($request->getMethod() === 'HEAD') {
            return $response;
        }

        if ($response->failed()) {
            throw $response->exception($request);
        }

        return $response;
    }
}
