<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Update
{
    use API;

    protected function updateAPICall(string $indexName, string $id, array $data): ElasticsearchResponse
    {
        $uri = Uri::withQueryValue(new Uri(sprintf('/%s/_update/%s', $indexName, $id)), 'format', 'json');

        $esRequest = new ElasticsearchRequest('POST', $uri);

        return $this->elasticsearchCall($esRequest);
    }
}
