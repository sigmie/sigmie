<?php

declare(strict_types=1);

namespace Sigmie\AI\Embedders;

use GuzzleHttp\Client;
use Http\Promise\Promise;
use Sigmie\AI\APIs\ApiResponse;
use Sigmie\AI\Contracts\Embedder;
use Sigmie\AI\VoyageEmbeddingRequest;

class VoyageProvider implements Embedder
{
    use VoyageEmbeddingRequest;

    protected Client $client;

    public function __construct(protected string $apiKey, protected string $model = 'voyage-3')
    {
        $this->client = new Client([
            'base_uri' => 'https://api.voyageai.com',
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function embed(string $text, int $dimensions): array
    {
        $response = $this->client->post('/v1/embeddings', [
            'json' => $this->requestBody([$text], $dimensions),
        ]);

        return ApiResponse::resizeEmbedding(ApiResponse::embeddings($response, 1)[0], $dimensions);
    }

    public function batchEmbed(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        foreach ($this->batchRequests($payload, 1000) as $group) {
            $response = $this->client->post('/v1/embeddings', [
                'json' => $this->requestBody($group['texts'], $group['dims']),
            ]);

            foreach (ApiResponse::embeddings($response, count($group['keys'])) as $index => $vector) {
                $payload[$group['keys'][$index]]['vector'] = ApiResponse::resizeEmbedding($vector, $group['dims']);
            }
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): Promise
    {
        $promise = $this->client->postAsync('/v1/embeddings', [
            'json' => $this->requestBody([$text], $dimensions),
        ]);

        return new EmbeddingPromise($promise, static fn (array $vector): array => ApiResponse::resizeEmbedding($vector, $dimensions));
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
