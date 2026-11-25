<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\AI\VoyageEmbeddingRequest;

class VoyageEmbeddingsApi implements EmbeddingsApi
{
    use VoyageEmbeddingRequest {
        supportsDimensions as public;
    }

    protected Client $client;

    public function __construct(
        string $apiKey,
        protected string $model = 'voyage-2'
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

    public function model(): string
    {
        return $this->model;
    }

    public function embed(string $text, int $dimensions): array
    {
        return $this->embedInput($text, $dimensions, 'document');
    }

    public function batchEmbed(array $payload): array
    {
        return $this->batchEmbedInput($payload, 'document');
    }

    public function batchEmbedQueries(array $payload): array
    {
        return $this->batchEmbedInput($payload, 'query');
    }

    private function batchEmbedInput(array $payload, string $inputType): array
    {
        if ($payload === []) {
            return [];
        }

        foreach ($this->batchRequests($payload, $this->maxBatchSize(), $inputType) as $group) {
            $dimensions = $group['dims'];

            $response = $this->client->post('/v1/embeddings', [
                RequestOptions::JSON => $this->requestBody($group['texts'], $dimensions, $inputType),
            ]);

            foreach (ApiResponse::embeddings($response, count($group['keys'])) as $index => $vector) {
                $payload[$group['keys'][$index]]['vector'] = ApiResponse::resizeEmbedding($vector, $dimensions);
            }
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): PromiseInterface
    {
        return $this->client->postAsync('/v1/embeddings', [
            RequestOptions::JSON => $this->requestBody($text, $dimensions, 'document'),
        ]);
    }

    /**
     * Create embeddings specifically for queries (optimized for search)
     */
    public function embedQuery(string $query, int $dimensions = 0): array
    {
        return $this->embedInput($query, $dimensions, 'query');
    }

    private function embedInput(string $text, int $dimensions, string $inputType): array
    {
        $response = $this->client->post('/v1/embeddings', [
            RequestOptions::JSON => $this->requestBody($text, $dimensions, $inputType),
        ]);

        return ApiResponse::resizeEmbedding(ApiResponse::embeddings($response, 1)[0], $dimensions);
    }

    public function maxBatchSize(): int
    {
        return 1000;
    }
}
