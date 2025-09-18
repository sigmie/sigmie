<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\RerankApi;

class VoyageRerankApi implements RerankApi
{
    protected Client $client;
    protected string $model;

    public function __construct(
        string $apiKey,
        string $model = 'rerank-1'
    ) {
        $this->model = $model;
        $this->client = new Client([
            'base_uri' => 'https://api.voyageai.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
        ]);
    }

    public function rerank(array $documents, string $query, array $options = []): array
    {
        $payload = [
            'model' => $this->model,
            'query' => $query,
            'documents' => $documents,
        ];

        // Merge additional options
        if (!empty($options)) {
            $payload = array_merge($payload, $options);
        }

        // Common options that can be passed:
        // - top_k: number of documents to return
        // - return_documents: whether to return the full documents
        // - truncation: whether to truncate long documents

        $response = $this->client->post('/v1/rerank', [
            RequestOptions::JSON => $payload
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['results'] ?? [];
    }

    /**
     * Convenience method to rerank with specific top_k
     */
    public function rerankTopK(array $documents, string $query, int $topK): array
    {
        return $this->rerank($documents, $query, [
            'top_k' => $topK,
            'return_documents' => true,
        ]);
    }
}