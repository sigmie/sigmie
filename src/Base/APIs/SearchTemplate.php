<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Http\Requests\Search as SearchRequest;
use Sigmie\Base\Http\Responses\Search as SearchResponse;

trait SearchTemplate
{
    use API;

    protected function templateAPICall(string $index, string $name, array $params): SearchResponse
    {
        $uri = new Uri(sprintf('/%s/_search/template', $index));

        $params = [
            'id' => $name,
            'params' => $params,
        ];

        $esRequest = new SearchRequest('POST', $uri, $params);

        return $this->elasticsearchCall($esRequest);
    }
}
