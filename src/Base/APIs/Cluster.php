<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Cluster
{
    use API;

    protected function clusterAPICall(string $path): ElasticsearchResponse
    {
        [$path, $query] = $this->encodedPath($path);
        $uri = (new Uri('/_cluster/'.$path))->withQuery($query ?? '');

        $esRequest = new ElasticsearchRequest('GET', $uri, null);

        return $this->elasticsearchCall($esRequest);
    }
}
