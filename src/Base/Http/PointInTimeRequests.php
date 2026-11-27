<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\Requests\Search as SearchRequest;
use Sigmie\Enums\SearchEngineType;

use function Sigmie\Functions\encoded_index_path;

class PointInTimeRequests
{
    public function __construct(
        private ElasticsearchConnection $connection,
    ) {}

    public function open(string $index, string $keepAlive = '1m'): ElasticsearchResponse
    {
        [$path] = encoded_index_path($index);
        $prefix = '/'.($path === '' ? '*' : $path);

        if ($this->connection->driver()->engine() === SearchEngineType::OpenSearch) {
            $uri = Uri::withQueryValues(new Uri($prefix.'/_search/point_in_time'), [
                'keep_alive' => $keepAlive,
                'allow_partial_pit_creation' => 'false',
            ]);

            return $this->call(new SearchRequest(
                'POST',
                $uri,
                [],
            ));
        }

        $uri = new Uri($prefix.'/_pit');

        return $this->call(new SearchRequest(
            'POST',
            $uri->withQuery('keep_alive='.rawurlencode($keepAlive)),
            [],
        ));
    }

    public function close(string $pitId): ElasticsearchResponse
    {
        $openSearch = $this->connection->driver()->engine() === SearchEngineType::OpenSearch;
        $request = $openSearch
            ? new SearchRequest('DELETE', new Uri('/_search/point_in_time'), ['pit_id' => [$pitId]])
            : new SearchRequest('DELETE', new Uri('/_pit'), ['id' => $pitId]);

        $response = $this->call($request);
        if ($response->json($openSearch ? 'pits.0.successful' : 'succeeded') === false) {
            throw new ElasticsearchException($response->json(), $response->code());
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function search(array $body): ElasticsearchResponse
    {
        $uri = new Uri('/_search');

        // The iterator must read the latest PIT ID before rejecting a partial page.
        return ($this->connection)(new ElasticsearchRequest('POST', $uri, $body));
    }

    private function call(SearchRequest $request): ElasticsearchResponse
    {
        return ($this->connection)($request);
    }
}
