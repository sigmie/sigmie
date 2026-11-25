<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\RerankApi;

use function Sigmie\Functions\rerank_texts;

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
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
        ]);
    }

    public function rerank(array $newIndexes, string $query, ?int $topK = null): array
    {
        if ($newIndexes === [] || $topK === 0) {
            return [];
        }

        $payload = [
            'model' => $this->model,
            'query' => $query,
            'documents' => rerank_texts($newIndexes),
        ];

        if ($topK !== null) {
            $payload['top_n'] = $topK;
        }

        $response = $this->client->post('/v2/rerank', [
            RequestOptions::JSON => $payload,
        ]);

        return ApiResponse::rerank($response, 'results', count($newIndexes));
    }
}
