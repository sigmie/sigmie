<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\RerankApi;

class LocalRerankApi implements RerankApi
{
    protected Client $client;

    protected string $model;

    public function __construct(
        string $baseUrl = 'http://localhost:7998',
        string $model = 'cross-encoder/ms-marco-MiniLM-L-6-v2'
    ) {
        $this->model = $model;
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
            RequestOptions::JSON => $payload
        ]);

        $json = json_decode($response->getBody()->getContents(), true);

        $data = $json['results'] ?? $json['data'] ?? [];

        ray($data)->green();

        return array_map(fn($result) => [
            'index' => $result['index'],
            'score' => $result['relevance_score'] ?? $result['score']
        ], $data);
    }
}
