<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Analyze
{
    use API;

    protected function analyzeAPICall(string $indexName, string $text, string $analyzer): ElasticsearchResponse
    {
        $uri = new Uri(sprintf('/%s/_analyze', $indexName));

        $request = new ElasticsearchRequest('POST', $uri, [
            'analyzer' => $analyzer,
            'text' => $text,
        ]);

        return $this->elasticsearchCall($request);
    }
}
