<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\RerankApi;

class InfinityRerankApi implements RerankApi
{
    protected Client $client;

    public function __construct(
        string $baseUrl = 'http://localhost:7998',
        protected string $model = 'cross-encoder/ms-marco-MiniLM-L-6-v2'
    ) {
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
        ]);
    }

    public function rerank(array $newIndexes, string $query, ?int $topK = null): array
    {
        $payload = [
            'model' => $this->model,
            'query' => $query,
            'documents' => $newIndexes,
        ];

        if ($topK !== null) {
            $payload['top_n'] = $topK;
        }

        $response = $this->client->post('/rerank', [
            RequestOptions::JSON => $payload,
        ]);

        $json = json_decode($response->getBody()->getContents(), true);

        $data = $json['results'] ?? $json['data'] ?? [];

        return array_map(fn ($result): array => [
            'index' => $result['index'],
            'score' => $result['relevance_score'] ?? $result['score'],
        ], $data);
    }
}
