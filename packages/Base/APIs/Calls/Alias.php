<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs\Calls;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Http\ElasticsearchResponse;

trait Alias
{
    use API;

    public function aliasAPICall(string $method, array $body): ElasticsearchResponse
    {
        $uri = new Uri('/_aliases');

        $esRequest = new ElasticsearchRequest($method, $uri, $body);

        return $this->httpCall($esRequest);
    }
}
