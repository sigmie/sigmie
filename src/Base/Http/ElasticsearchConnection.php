<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Elastic\Transport\Exception\NoNodeAvailableException;
use Http\Promise\Promise;
use Http\Promise\RejectedPromise;
use RuntimeException;
use Sigmie\Base\Contracts\ElasticsearchConnection as ElasticsearchConnectionInterface;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Http\Contracts\JSONClient as JSONClientInterface;
use Sigmie\Http\Contracts\JSONResponse;
use Throwable;

class ElasticsearchConnection implements ElasticsearchConnectionInterface
{
    public function __construct(
        protected JSONClientInterface $http,
        protected SearchEngine $driver = new Elasticsearch,
        protected bool $serverless = false,
        protected ?object $networkError = null,
    ) {}

    public function isServerless(): bool
    {
        return $this->serverless;
    }

    public function driver(): SearchEngine
    {
        return $this->driver;
    }

    public function promise(ElasticsearchRequest $request): Promise
    {
        try {
            $promise = $this->http->promise($request);
        } catch (Throwable $throwable) {
            return new RejectedPromise($this->withCurlError($throwable));
        }

        return $promise->then(
            fn (JSONResponse $res): ElasticsearchResponse => $this->response($request, $res),
            function (mixed $reason): never {
                throw $this->withCurlError($reason instanceof Throwable ? $reason : new RuntimeException('Request failed'));
            }
        );
    }

    public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
    {
        $uri = $request->getUri();

        $request = $request->withUri($uri);

        try {
            $jsonResponse = $this->http->request($request);
        } catch (Throwable $throwable) {
            throw $this->withCurlError($throwable);
        }

        return $this->response($request, $jsonResponse);
    }

    private function response(ElasticsearchRequest $request, JSONResponse $jsonResponse): ElasticsearchResponse
    {
        $response = $request->response($jsonResponse->psr());

        if ($request->getMethod() === 'HEAD' && $response->code() === 404) {
            return $response;
        }

        if ($response->failed()) {
            throw $response->exception($request);
        }

        return $response;
    }

    private function withCurlError(Throwable $exception): Throwable
    {
        $network = $this->networkError->error ?? null;

        if (is_object($this->networkError)) {
            $this->networkError->error = null;
        }

        if (! $exception instanceof NoNodeAvailableException || $exception->getPrevious() instanceof Throwable) {
            return $exception;
        }

        if (! $network instanceof Throwable) {
            return $exception;
        }

        // The transport marks the last live node dead, then nextNode() throws
        // on its own. That throw has no previous. docs/installation.md keeps
        // the cURL error on getPrevious(). The dead node stays dead.
        return new NoNodeAvailableException($exception->getMessage(), (int) $exception->getCode(), $network);
    }
}
