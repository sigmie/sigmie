<?php

declare(strict_types=1);

namespace Sigmie\AI\Embedders;

use Closure;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Http\Promise\Promise;
use Psr\Http\Message\ResponseInterface;
use Sigmie\AI\APIs\ApiResponse;
use Throwable;

class EmbeddingPromise implements Promise
{
    private PromiseInterface $promise;

    public function __construct(PromiseInterface $promise, ?Closure $transform = null)
    {
        $this->promise = self::chain($promise, static function (ResponseInterface $response) use ($transform): array {
            $vector = ApiResponse::embeddings($response, 1)[0];

            return ['_embeddings' => $transform === null ? $vector : $transform($vector)];
        });
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): Promise
    {
        $child = clone $this;
        $child->promise = self::chain($this->promise, $onFulfilled, $onRejected);

        return $child;
    }

    private static function chain(PromiseInterface $promise, ?callable $onFulfilled, ?callable $onRejected = null): PromiseInterface
    {
        $invoke = static function (callable $callback, mixed $value): mixed {
            $result = $callback($value);

            if ($result instanceof self) {
                return $result->promise;
            }

            return $result instanceof Promise ? Create::promiseFor($result) : $result;
        };

        if ($promise->getState() === PromiseInterface::PENDING) {
            // Forward settlement from any promise returned by a callback.
            return $promise->then(
                $onFulfilled === null ? null : fn ($value) => $invoke($onFulfilled, $value),
                $onRejected === null ? null : fn ($reason) => $invoke($onRejected, Create::exceptionFor($reason)),
            )->then();
        }

        // HTTPlug runs callbacks immediately when the promise is already settled.
        try {
            $value = $promise->wait();
            $callback = $onFulfilled;
        } catch (Throwable $reason) {
            $value = $reason;
            $callback = $onRejected;
        }

        if ($callback === null) {
            return $promise;
        }

        try {
            return Create::promiseFor($invoke($callback, $value));
        } catch (Throwable $reason) {
            return Create::rejectionFor($reason);
        }
    }

    public function getState(): string
    {
        return $this->promise->getState();
    }

    public function wait($unwrap = true): mixed
    {
        return $this->promise->wait($unwrap);
    }
}
