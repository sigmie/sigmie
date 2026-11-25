<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\ElasticsearchException;

use function Sigmie\Functions\encoded_index_path;

trait API
{
    protected ElasticsearchConnection $elasticsearchConnection;

    public function setElasticsearchConnection(ElasticsearchConnection $connection): void
    {
        $this->elasticsearchConnection = $connection;
    }

    public function getElasticsearchConnection(): ElasticsearchConnection
    {
        return $this->elasticsearchConnection;
    }

    protected function elasticsearchCall(ElasticsearchRequest $request, ?string $responseType = null): ElasticsearchResponse
    {
        $response = ($this->elasticsearchConnection)($request);

        return $responseType === null ? $response : $this->typedResponse($request, $response, $responseType);
    }

    /** @param class-string<ElasticsearchResponse> $type */
    protected function typedResponse(ElasticsearchRequest $request, ElasticsearchResponse $response, string $type): ElasticsearchResponse
    {
        if (! $response instanceof $type) {
            $response = $request->response($response->psr());
        }

        if ($response->failed()) {
            throw $response->exception($request);
        }

        return $response;
    }

    protected function assertNoFailedShards(ElasticsearchResponse $response): void
    {
        if ($response->json('_shards.failed') > 0) {
            throw new ElasticsearchException($response->json(), $response->code());
        }
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    protected function encodedPath(string $value): array
    {
        return encoded_index_path($value);
    }

    protected function indexUri(string $index, string $endpoint): Uri
    {
        $path = str_replace(['%2C', '%2A'], [',', '*'], rawurlencode($index));

        return new Uri(($path === '' ? '' : '/'.$path).'/'.$endpoint);
    }
}
