<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\EmbeddingsApi;

class LocalEmbeddingsApi implements EmbeddingsApi
{
    protected Client $client;

    protected string $model;

    public function __construct(
        string $baseUrl = 'http://localhost:7997',
        string $model = 'BAAI/bge-small-en-v1.5'
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

    public function embed(string $text, int $dimensions): array
    {
        $response = $this->client->post('/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $text,
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['data'][0]['embedding'];
    }

    public function batchEmbed(array $payload): array
    {
        if (count($payload) === 0) {
            return [];
        }

        $texts = array_map(fn($item) => $item['text'] ?? '', $payload);

        $response = $this->client->post('/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $texts,
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        foreach ($data['data'] as $index => $result) {
            $payload[$index]['vector'] = $result['embedding'];
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): Promise
    {
        return $this->client->postAsync('/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $text,
            ]
        ]);
    }

    public function model(): string
    {
        return $this->model;
    }
}
