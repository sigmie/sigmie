<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\EmbeddingsApi;

use function Sigmie\Functions\batch_slots;

class InfinityEmbeddingsApi implements EmbeddingsApi
{
    protected Client $client;

    public function __construct(
        string $baseUrl = 'http://localhost:7997',
        protected string $model = 'BAAI/bge-small-en-v1.5'
    ) {
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/').'/',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
        ]);
    }

    public function embed(string $text, int $dimensions): array
    {
        $response = $this->client->post('embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $text,
                'dimensions' => $dimensions,
            ],
        ]);

        return ApiResponse::embeddings($response, 1)[0];
    }

    public function batchEmbed(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        foreach (batch_slots($payload, null, $this->maxBatchSize()) as $group) {
            $response = $this->client->post('embeddings', [
                RequestOptions::JSON => [
                    'model' => $this->model,
                    'input' => $group['texts'],
                    ...($group['dims'] === null ? [] : ['dimensions' => $group['dims']]),
                ],
            ]);

            foreach (ApiResponse::embeddings($response, count($group['keys'])) as $index => $vector) {
                $payload[$group['keys'][$index]]['vector'] = $vector;
            }
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): PromiseInterface
    {
        return $this->client->postAsync('embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $text,
                'dimensions' => $dimensions,
            ],
        ]);
    }

    public function model(): string
    {
        return $this->model;
    }

    public function maxBatchSize(): int
    {
        return 2048;
    }
}
