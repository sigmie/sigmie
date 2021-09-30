<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Doc
{
    use API;

    public function docAPICall(string $index, string $id, string $method = 'GET'): ElasticsearchResponse
    {
        $uri = new Uri("{$index}/_doc/{$id}");

        $esRequest = new ElasticsearchRequest($method, $uri);

        return $this->httpCall($esRequest);
    }
}
