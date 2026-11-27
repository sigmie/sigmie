<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Count
{
    use API;

    protected function countAPICall(string $indexName): ElasticsearchResponse
    {
        $uri = Uri::withQueryValue($this->indexUri($indexName, '_count'), 'format', 'json');

        $esRequest = new ElasticsearchRequest('GET', $uri);

        $response = $this->elasticsearchCall($esRequest);

        $shards = $response->json('_shards') ?? [];
        if (($shards['failed'] ?? 0) > 0 || (isset($shards['successful'], $shards['total']) && $shards['successful'] < $shards['total'])) {
            throw new ElasticsearchException($response->json(), $response->code());
        }

        return $response;
    }
}
