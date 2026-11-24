<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Analyze
{
    use API;

    protected function analyzeAPICall(string $indexName, string $text, string $analyzer): ElasticsearchResponse
    {
        $uri = $this->indexUri($indexName, '_analyze');

        $request = new ElasticsearchRequest('POST', $uri, [
            'analyzer' => $analyzer,
            'text' => $text,
        ]);

        return $this->elasticsearchCall($request);
    }
}
