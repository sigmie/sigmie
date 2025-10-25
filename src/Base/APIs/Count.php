<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Count
{
    use API;

    protected function countAPICall(string $indexName): ElasticsearchResponse
    {
        $uri = Uri::withQueryValue(new Uri(sprintf('/%s/_count', $indexName)), 'format', 'json');

        $esRequest = new ElasticsearchRequest('GET', $uri);

        return $this->elasticsearchCall($esRequest);
    }
}
