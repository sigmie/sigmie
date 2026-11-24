<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use Sigmie\Base\Http\Requests\Search as SearchRequest;
use Sigmie\Base\Http\Responses\Search as SearchResponse;

trait SearchTemplate
{
    use API;

    protected function templateAPICall(string $index, string $name, array $params): SearchResponse
    {
        $uri = $this->indexUri($index, '_search/template');

        $params = [
            'id' => $name,
            'params' => (object) $params,
        ];

        $esRequest = new SearchRequest('POST', $uri, $params);

        return $this->elasticsearchCall($esRequest, SearchResponse::class);
    }
}
