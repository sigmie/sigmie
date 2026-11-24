<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Http\ElasticsearchResponse as HttpElasticsearchResponse;

trait Update
{
    use Bulk;

    protected function updateAPICall(string $indexName, string $id, array $data): ElasticsearchResponse
    {
        if ($id === '.' || $id === '..') {
            $bulk = $this->bulkAPICall($indexName, [['update' => ['_id' => $id]], $data]);
            $bulk->assertItemsSucceeded();

            $item = $bulk->items()[0]['update'];
            $status = $item['status'];
            unset($item['status']);

            return HttpElasticsearchResponse::fromPsrResponse(new Response(
                $status,
                ['Content-Type' => 'application/json'],
                json_encode($item, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            ));
        }

        $uri = Uri::withQueryValue(new Uri(sprintf('/%s/_update/%s', rawurlencode($indexName), rawurlencode($id))), 'format', 'json');

        $esRequest = new ElasticsearchRequest('POST', $uri, $data);

        $response = $this->elasticsearchCall($esRequest);
        $this->assertNoFailedShards($response);

        return $response;
    }
}
