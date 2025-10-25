<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\RerankApi;

class VoyageRerankApi implements RerankApi
{
    protected Client $client;

    public function __construct(
        string $apiKey,
        protected string $model = 'rerank-2.5-lite'
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.voyageai.com',
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
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

        // Merge additional options
        if ($topK !== null) {
            $payload['top_k'] = $topK;
        }

        // Common options that can be passed:
        // - top_k: number of documents to return
        // - return_documents: whether to return the full documents
        // - truncation: whether to truncate long documents

        $response = $this->client->post('/v1/rerank', [
            RequestOptions::JSON => $payload,
        ]);

        $json = json_decode($response->getBody()->getContents(), true);

        $data = $json['data'];

        // Reorder documents based on reranking results using array_map
        $newIndexes = array_map(fn ($result): array => [
            'index' => $result['index'],
            'score' => $result['relevance_score'],
        ], $data);

        return $newIndexes;
    }
}
