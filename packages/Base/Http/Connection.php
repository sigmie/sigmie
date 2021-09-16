<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use EventSauce\BackOff\BackOffStrategy;
use EventSauce\BackOff\FibonacciBackOffStrategy;
use GuzzleHttp\Exception\GuzzleException;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\HttpConnection as ConnectionInterface;
use Sigmie\Http\Contracts\JSONClient as JSONClientInterface;
use Sigmie\Http\Contracts\JSONResponse;

class Connection implements ConnectionInterface
{
    protected JSONClientInterface $http;

    protected int $retries = 3;

    protected BackOffStrategy $backOff;

    public function __construct(JsonClientInterface $http)
    {
        $this->http = $http;
        $this->backOff = new FibonacciBackOffStrategy(
            initialDelayMs: 0,
            maxTries: $this->retries,
            maxDelay: 1000000,
        );
    }

    public function __invoke(ElasticsearchRequest $request, array $options = []): ElasticsearchResponse
    {
        $uri = $request->getUri();

        $request = $request->withUri($uri);

        $jsonResponse = $this->call(fn () => $this->http->request($request));

        $response = $request->response($jsonResponse->psr());

        if ($response->failed()) {
            throw $response->exception($request);
        }

        return $response;
    }

    protected function call(callable $callable): JSONResponse
    {
        $tries = 0;

        start:
        try {
            ++$tries;
            return $callable();
        } catch (GuzzleException $throwable) {
            $this->backOff->backOff($tries, $throwable);
            goto start;
        }
    }
}
