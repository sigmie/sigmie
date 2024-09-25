<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Tasks
{
    use API;

    public function tasksAPICall(): ElasticsearchResponse
    {
        $uri = new Uri('/_tasks');

        $esRequest = new ElasticsearchRequest('GET', $uri);

        return $this->elasticsearchCall($esRequest);
    }
}
