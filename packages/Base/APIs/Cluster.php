<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Http\ElasticsearchResponse;

trait Cluster
{
    use API;

    protected function clusterAPICall(string $path): ElasticsearchResponse
    {
        $uri = new Uri('/_cluster'.$path);

        $esRequest = new ElasticsearchRequest('GET', $uri, []);

        return $this->httpCall($esRequest);
    }
}
