<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\RerankApi;

use function Sigmie\Functions\rerank_batches;

class InfinityRerankApi implements RerankApi
{
    protected Client $client;

    public function __construct(
        string $baseUrl = 'http://localhost:7998',
        protected string $model = 'cross-encoder/ms-marco-MiniLM-L-6-v2'
    ) {
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/').'/',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
        ]);
    }

    public function rerank(array $newIndexes, string $query, ?int $topK = null): array
    {
        return rerank_batches($newIndexes, $topK, 2048, function (array $documents, ?int $limit) use ($query): array {
            $response = $this->client->post('rerank', [
                RequestOptions::JSON => [
                    'model' => $this->model,
                    'query' => $query,
                    'documents' => $documents,
                    ...($limit === null ? [] : ['top_n' => $limit]),
                ],
            ]);

            return ApiResponse::rerank($response, 'results', count($documents));
        });
    }
}
