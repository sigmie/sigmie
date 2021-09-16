<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Cat
{
    use API;

    protected function catAPICall(string $path, string $method): ElasticsearchResponse
    {
        $uri = Uri::withQueryValue(new Uri('/_cat' . $path), 'format', 'json');

        $esRequest = new ElasticsearchRequest($method, $uri, []);

        return $this->httpCall($esRequest);
    }
}
