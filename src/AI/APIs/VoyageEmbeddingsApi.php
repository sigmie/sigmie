<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\EmbeddingsApi;

class VoyageEmbeddingsApi implements EmbeddingsApi
{
    protected Client $client;

    public function __construct(
        string $apiKey,
        protected string $model = 'voyage-2'
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.voyageai.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
        ]);
    }

    public function model(): string
    {
        return $this->model;
    }

    public function embed(string $text, int $dimensions): array
    {
        $response = $this->client->post('/v1/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $text,
                'input_type' => 'document', // Can be 'query' or 'document'
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Voyage returns embeddings in a slightly different format
        $embedding = $data['data'][0]['embedding'];

        // If dimensions are specified and different from the model's output, truncate or pad
        if ($dimensions > 0 && count($embedding) !== $dimensions) {
            if (count($embedding) > $dimensions) {
                // Truncate to requested dimensions
                $embedding = array_slice($embedding, 0, $dimensions);
            } else {
                // Pad with zeros if needed (though this is unusual)
                $embedding = array_pad($embedding, $dimensions, 0.0);
            }
        }

        return $embedding;
    }

    public function batchEmbed(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        $texts = array_map(fn($item) => $item['text'] ?? '', $payload);

        $dimensions = $payload[0]['dims'] ?? 0;

        $response = $this->client->post('/v1/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $texts,
                'input_type' => 'document',
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        foreach ($data['data'] as $index => $result) {
            $embedding = $result['embedding'];

            // Apply dimension adjustment if needed
            if ($dimensions > 0 && count($embedding) !== $dimensions) {
                if (count($embedding) > $dimensions) {
                    $embedding = array_slice($embedding, 0, $dimensions);
                } else {
                    $embedding = array_pad($embedding, $dimensions, 0.0);
                }
            }

            $payload[$index]['vector'] = $embedding;
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): Promise
    {
        return $this->client->postAsync('/v1/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $text,
                'input_type' => 'document',
            ]
        ]);
    }

    /**
     * Create embeddings specifically for queries (optimized for search)
     */
    public function embedQuery(string $query, int $dimensions = 0): array
    {
        $response = $this->client->post('/v1/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $query,
                'input_type' => 'query', // Optimized for queries
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $embedding = $data['data'][0]['embedding'];

        if ($dimensions > 0 && count($embedding) !== $dimensions) {
            if (count($embedding) > $dimensions) {
                $embedding = array_slice($embedding, 0, $dimensions);
            } else {
                $embedding = array_pad($embedding, $dimensions, 0.0);
            }
        }

        return $embedding;
    }
}
