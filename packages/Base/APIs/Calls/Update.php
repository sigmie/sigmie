<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs\Calls;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Http\JSONRequest;

trait Update
{
    use API;

    protected function updateAPICall(string $indexName, string $id, array $data): ElasticsearchResponse
    {
        $uri = Uri::withQueryValue(new Uri("/{$indexName}/_update/{$id}"), 'format', 'json');

        $esRequest = new JSONRequest('POST', $uri);

        return $this->httpCall($esRequest, ElasticsearchResponse::class);
    }
}
