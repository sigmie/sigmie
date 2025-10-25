<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Http\Requests\Search as SearchRequest;
use Sigmie\Base\Http\Responses\Search as SearchResponse;

trait Search
{
    use API;

    protected function searchAPICall(string $index, array $query, ?string $scroll = null): SearchResponse
    {
        $esRequest = $this->searchRequest($index, $query, $scroll);

        return $this->elasticsearchCall($esRequest);
    }

    protected function scrollAPICall(string $scrollId, string $scroll): SearchResponse
    {
        $uri = new Uri('/_search/scroll');

        return $this->elasticsearchCall(new SearchRequest('POST', $uri, [
            'scroll' => $scroll,
            'scroll_id' => $scrollId,
        ]));
    }

    protected function searchRequest(string $index, array $query, ?string $scroll = null): SearchRequest
    {
        $uri = new Uri(sprintf('/%s/_search', $index));

        if ($scroll) {
            $uri = $uri->withQuery('scroll='.$scroll);
        }

        return new SearchRequest('POST', $uri, $query);
    }

    protected function searchTemplateRequest(string $index, array $query): SearchResponse
    {
        $uri = new Uri(sprintf('/%s/_search/template', $index));

        return $this->elasticsearchCall(new SearchRequest('POST', $uri, $query));
    }
}
