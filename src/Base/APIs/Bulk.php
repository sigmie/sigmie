<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Http\Requests\Bulk as BulkRequest;
use Sigmie\Base\Http\Responses\Bulk as BulkResponse;

trait Bulk
{
    use API;

    protected bool $requireAlias = false;

    protected function bulkAPICall(string $indexName, array $data, string $refresh = 'false'): BulkResponse
    {
        $uri = $this->indexUri($indexName, '_bulk');

        $uri = Uri::withQueryValue($uri, 'refresh', $refresh);

        if ($this->requireAlias) {
            $uri = Uri::withQueryValue($uri, 'require_alias', 'true');
        }

        $request = new BulkRequest('POST', $uri, $data);

        return $this->elasticsearchCall($request, BulkResponse::class);
    }
}
