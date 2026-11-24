<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Http\Responses\Reindex as ReindexResponse;

trait Index
{
    use API;

    public function indexAPICall(string $index, string $method, ?array $body = null): ElasticsearchResponse
    {
        // Names sit in the path. Encode each segment and keep / , * and the query.
        [$path, $query] = $this->encodedPath($index);

        $uri = new Uri('/'.$path);

        if ($query !== null) {
            $uri = $uri->withQuery($query);
        }

        $esRequest = new ElasticsearchRequest($method, $uri, $body);

        $response = $this->elasticsearchCall($esRequest);
        $this->assertNoFailedShards($response);

        if ($esRequest->getMethod() === 'PUT' && ! str_contains($path, '/') && $response->json('shards_acknowledged') === false) {
            throw new ElasticsearchException($response->json(), $response->code());
        }

        $rolloverSkipped = in_array('_rollover', explode('/', $path), true) && $response->json('rolled_over') === false;
        if (! $rolloverSkipped && in_array($esRequest->getMethod(), ['PUT', 'POST'], true) && $response->json('acknowledged') === false) {
            throw new ElasticsearchException($response->json(), $response->code());
        }

        $taskEndpoint = $path === '_tasks' || str_starts_with($path, '_tasks/');
        if ($taskEndpoint && ($response->json('node_failures') || $response->json('task_failures'))) {
            throw new ElasticsearchException($response->json(), $response->code());
        }

        if ($taskEndpoint || $path === '_reindex'
            || str_ends_with($path, '/_delete_by_query') || str_ends_with($path, '/_update_by_query')) {
            ReindexResponse::assertComplete($response->json(), $response->code());
        }

        return $response;
    }
}
