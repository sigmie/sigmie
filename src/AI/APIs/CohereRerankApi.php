<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\RerankApi;

class CohereRerankApi implements RerankApi
{
    protected Client $client;

    public function __construct(
        string $apiKey,
        protected string $model = 'rerank-v3.5'
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.cohere.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
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

        $response = $this->client->post('/v2/rerank', [
            RequestOptions::JSON => $payload
        ]);

        $json = json_decode($response->getBody()->getContents(), true);

        $data = $json['results'];

        return array_map(fn($result): array => [
            'index' => $result['index'],
            'score' => $result['relevance_score']
        ], $data);
    }
}
