<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs\Calls;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\APIs\Responses\Bulk as BulkResponse;
use Sigmie\Base\Contracts\API;
use Sigmie\Http\NdJSONRequest;

trait Bulk
{
    use API;

    protected function bulkAPICall(string $indexName, array $data, bool $async = false): BulkResponse
    {
        $uri = new Uri("/{$indexName}/_bulk");

        if (!$async) {
            $uri = Uri::withQueryValue($uri, 'refresh', 'wait_for');
        }

        $request = new NdJSONRequest('POST', $uri, $data);

        return $this->httpCall($request, \Sigmie\Base\APIs\Responses\Bulk::class);
    }
}
