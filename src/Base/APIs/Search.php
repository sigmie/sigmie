<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\PointInTimeRequests;
use Sigmie\Base\Http\Requests\Search as SearchRequest;
use Sigmie\Base\Http\Responses\Search as SearchResponse;

trait Search
{
    use API;

    protected function searchAPICall(string $index, array $query): SearchResponse
    {
        $esRequest = $this->searchRequest($index, $query);

        return $this->elasticsearchCall($esRequest);
    }

    protected function pitSearchAPICall(array $body): ElasticsearchResponse
    {
        return $this->pitRequests()->search($body);
    }

    protected function openPointInTimeAPICall(string $index, string $keepAlive = '1m'): ElasticsearchResponse
    {
        return $this->pitRequests()->open($index, $keepAlive);
    }

    protected function closePointInTimeAPICall(string $pitId): ElasticsearchResponse
    {
        return $this->pitRequests()->close($pitId);
    }

    protected function pitRequests(): PointInTimeRequests
    {
        return new PointInTimeRequests($this->elasticsearchConnection);
    }

    protected function searchRequest(string $index, array $query): SearchRequest
    {
        $uri = new Uri(sprintf('/%s/_search', $index));

        return new SearchRequest('POST', $uri, $query);
    }

    protected function searchTemplateRequest(string $index, array $query): SearchResponse
    {
        $uri = new Uri(sprintf('/%s/_search/template', $index));

        return $this->elasticsearchCall(new SearchRequest('POST', $uri, $query));
    }
}
