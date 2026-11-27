<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\Requests\MSearch as RequestsMSearch;
use Sigmie\Base\Http\Responses\Search;

trait MSearch
{
    use API;

    protected function msearchAPICall(array $body): ElasticsearchResponse
    {
        $uri = new Uri('/_msearch');

        $esRequest = new RequestsMSearch('POST', $uri, $body);

        return $this->elasticsearchCall($esRequest);
    }

    /**
     * The per-search responses of an _msearch call. A failed or partial item throws, because
     * _msearch answers 200 even when one of its searches failed.
     *
     * @return list<array>
     */
    protected function msearchResponses(ElasticsearchResponse $response): array
    {
        $responses = $response->json('responses') ?? [];

        foreach ($responses as $item) {
            if (isset($item['error']) || ($item['status'] ?? 200) >= 400 || Search::isPartial($item)) {
                throw new ElasticsearchException($item, $item['status'] ?? $response->code());
            }
        }

        return $responses;
    }
}
