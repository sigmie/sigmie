<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Elastic\Transport\Async\OnFailureInterface;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Elastic\Transport\NodePool\Node;
use Elastic\Transport\Transport;
use Exception;
use Http\Client\Exception\HttpException;
use Http\Promise\Promise;
use Http\Promise\RejectedPromise;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sigmie\Http\Contracts\JSONRequest;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONResponse;
use Throwable;

final class TransportClient extends JSONClient
{
    public function __construct(Transport $http, private bool $httpErrors = false)
    {
        parent::__construct($http);
    }

    public function promise(JSONRequest $jsonRequest): Promise
    {
        $transport = $this->http;
        $failure = null;
        if ($transport->getRetries() === 0) {
            // Elastic 8.x skips its failure hook with zero retries. Enter the hook once
            // and throw before redispatch so the selected node and cause remain available.
            $failure = new class implements OnFailureInterface
            {
                public ?Exception $cause = null;

                public function failure(Exception $e, RequestInterface $request, int $count, ?Node $node = null): never
                {
                    if ($e instanceof NetworkExceptionInterface) {
                        $node?->markAlive(false);
                        $e = new NodeConnectionException($e->getMessage(), 0, $e->getPrevious() ?? $e);
                    }

                    $this->cause = $e;
                    throw $e;
                }
            };
            $transport = (clone $transport)->setRetries(1)->setAsyncOnFailure($failure);
        }

        try {
            $promise = $transport->sendAsyncRequest($jsonRequest);
        } catch (NoNodeAvailableException $exception) {
            return new RejectedPromise($exception);
        }

        // Elastic treats a rejected transport promise as an unreachable node.
        // Apply HTTP error handling once the node has delivered its response.
        $sent = $transport->getLastRequest() ?? $jsonRequest;

        return $promise->then(function (ResponseInterface $response) use ($sent): JSONResponse {
            if ($this->httpErrors && $response->getStatusCode() >= 400) {
                throw HttpException::create($sent, $response);
            }

            return JSONResponse::fromPsrResponse($response);
        }, static function (Throwable $error) use ($failure): never {
            throw $failure->cause ?? $error;
        });
    }
}
