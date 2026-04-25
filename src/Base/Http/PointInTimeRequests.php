<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\Requests\Search as SearchRequest;
use Sigmie\Enums\SearchEngineType;

final class PointInTimeRequests
{
    public function __construct(
        private ElasticsearchConnection $connection,
    ) {}

    public function open(string $index, string $keepAlive = '1m'): ElasticsearchResponse
    {
        if ($this->connection->driver()->engine() === SearchEngineType::OpenSearch) {
            $uri = new Uri(sprintf('/%s/_search/point_in_time', $index));

            return $this->call(new SearchRequest(
                'POST',
                $uri->withQuery('keep_alive='.rawurlencode($keepAlive)),
                [],
            ));
        }

        $uri = new Uri(sprintf('/%s/_pit', $index));

        return $this->call(new SearchRequest(
            'POST',
            $uri->withQuery('keep_alive='.rawurlencode($keepAlive)),
            [],
        ));
    }

    public function close(string $pitId): ElasticsearchResponse
    {
        if ($this->connection->driver()->engine() === SearchEngineType::OpenSearch) {
            $uri = new Uri('/_search/point_in_time');

            return $this->call(new SearchRequest('DELETE', $uri, [
                'pit_id' => [$pitId],
            ]));
        }

        $uri = new Uri('/_pit');

        return $this->call(new SearchRequest('DELETE', $uri, [
            'id' => $pitId,
        ]));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function search(array $body): ElasticsearchResponse
    {
        $uri = new Uri('/_search');

        return $this->call(new SearchRequest('POST', $uri, $body));
    }

    private function call(SearchRequest $request): ElasticsearchResponse
    {
        return ($this->connection)($request);
    }
}
