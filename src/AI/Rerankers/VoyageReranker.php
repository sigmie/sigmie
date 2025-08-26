<?php

declare(strict_types=1);

namespace Sigmie\AI\Rerankers;

use GuzzleHttp\Client;
use Sigmie\AI\Contracts\Reranker;

class VoyageReranker implements Reranker
{
    protected Client $client;
    protected string $apiKey;
    protected string $model;

    public function __construct(string $apiKey, string $model = 'rerank-2')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->client = new Client([
            'base_uri' => 'https://api.voyageai.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    public function rerank(array $documents, string $queryString, ?int $topK = null): array
    {
        if (count($documents) === 0) {
            return [];
        }

        $requestData = [
            'model' => $this->model,
            'query' => $queryString,
            'documents' => $documents,
        ];

        if ($topK !== null) {
            $requestData['top_k'] = $topK;
        }

        $response = $this->client->post('/v1/rerank', [
            'json' => $requestData
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        // Voyage returns results sorted by relevance score
        $results = [];
        foreach ($data['data'] as $item) {
            $results[] = [
                'index' => $item['index'],
                'score' => $item['relevance_score'],
                'document' => $documents[$item['index']] ?? null
            ];
        }

        return $results;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}