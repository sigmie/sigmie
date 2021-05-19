<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs\Calls;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\APIs\Requests\BulkRequest;
use Sigmie\Base\APIs\Responses\Bulk as BulkResponse;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Http\NdJSONRequest;

trait Analyze
{
    use API;

    protected function analyzeAPICall(string $indexName, string $text, string $analyzer): ElasticsearchResponse
    {
        $uri = new Uri("/{$indexName}/_analyze");

        $request = new ElasticsearchRequest('POST', $uri, [
            'analyzer' => $analyzer,
            'text' => $text
        ]);

        return $this->httpCall($request);
    }
}
