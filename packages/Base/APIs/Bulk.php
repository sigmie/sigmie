<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Requests\Bulk as BulkRequest;
use Sigmie\Base\Http\Responses\Bulk as BulkResponse;

use function Sigmie\Helpers\refresh_value;

trait Bulk
{
    use API;

    protected function bulkAPICall(string $indexName, array $data, string $refresh = 'false'): BulkResponse
    {
        $uri = new Uri("/{$indexName}/_bulk");

        if (is_null(refresh_value())) {
            $uri = Uri::withQueryValue($uri, 'refresh', $refresh);
        } else {
            $uri = Uri::withQueryValue($uri, 'refresh', refresh_value());
        }

        $request = new BulkRequest('POST', $uri, $data);

        /** @var  BulkResponse */
        return $this->httpCall($request);
    }
}
