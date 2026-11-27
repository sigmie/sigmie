<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\Requests\Delete as RequestsDelete;

trait Delete
{
    use API;

    protected function deleteAPICall(string $index, string $identifier, string $refresh = 'false'): ElasticsearchResponse
    {
        $uri = new Uri(sprintf('/%s/_doc/%s', rawurlencode($index), rawurlencode($identifier)));

        $uri = Uri::withQueryValue($uri, 'refresh', $refresh);

        $esRequest = new RequestsDelete($uri);

        $response = $this->elasticsearchCall($esRequest);
        $this->assertNoFailedShards($response);

        return $response;
    }
}
