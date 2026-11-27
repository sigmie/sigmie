<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Cat
{
    use API;

    protected function catAPICall(string $path, string $method): ElasticsearchResponse
    {
        [$path, $query] = $this->encodedPath($path);

        $uri = new Uri('/_cat/'.$path);

        if ($query !== null) {
            $uri = $uri->withQuery($query);
        }

        $uri = Uri::withQueryValue($uri, 'format', 'json');

        $esRequest = new ElasticsearchRequest($method, $uri, null);

        return $this->elasticsearchCall($esRequest);
    }
}
