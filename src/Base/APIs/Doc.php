<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Doc
{
    use API;

    public function docAPICall(string $index, string $id, string $method = 'GET'): ElasticsearchResponse
    {
        $uri = new Uri(sprintf('/%s/_doc/%s', $index, $id));

        $esRequest = new ElasticsearchRequest($method, $uri);

        return $this->elasticsearchCall($esRequest);
    }
}
