<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Explain
{
    use API;

    protected function explainAPICall(string $index, array $query, string $_id): ElasticsearchResponse
    {
        $uri = new Uri("/{$index}/_explain/{$_id}");

        $esRequest = new ElasticsearchRequest('POST', $uri, ['query' => $query]);

        return $this->elasticsearchCall($esRequest);
    }
}
