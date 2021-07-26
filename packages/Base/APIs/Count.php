<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;

trait Count
{
    use API;

    protected function countAPICall(string $indexName): ElasticsearchResponse
    {
        $uri = Uri::withQueryValue(new Uri("/$indexName/_count"), 'format', 'json');

        $esRequest = new ElasticsearchRequest('GET', $uri);

        return $this->httpCall($esRequest);
    }
}
