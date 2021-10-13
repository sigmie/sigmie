<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;


trait Delete
{
    use API;

    protected function deleteAPICall(string $index, string $identifier, string $refresh = 'false'): ElasticsearchResponse
    {
        $uri = new Uri("/{$index}/_doc/{$identifier}");

        $uri = Uri::withQueryValue($uri, 'refresh', $refresh);

        $esRequest = new ElasticsearchRequest('DELETE', $uri);

        return $this->httpCall($esRequest);
    }
}
