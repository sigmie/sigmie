<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Enums\CohereInputType;

class CohereEmbeddingsApi implements EmbeddingsApi
{
    protected Client $client;
    protected string $model;
    protected CohereInputType $inputType;

    public function __construct(
        string $apiKey,
        CohereInputType $inputType,
        string $model = 'embed-english-v3.0'
    ) {
        $this->model = $model;
        $this->inputType = $inputType;
        $this->client = new Client([
            'base_uri' => 'https://api.cohere.ai',
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
        $response = $this->client->post('/v1/embed', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'texts' => [$text],
                'input_type' => $this->inputType->value,
                'embedding_types' => ['float'],
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        $embedding = $data['embeddings']['float'][0];

        if ($dimensions > 0 && count($embedding) !== $dimensions) {
            if (count($embedding) > $dimensions) {
                $embedding = array_slice($embedding, 0, $dimensions);
            } else {
                $embedding = array_pad($embedding, $dimensions, 0.0);
            }
        }

        return $embedding;
    }

    public function batchEmbed(array $payload): array
    {
        if (count($payload) === 0) {
            return [];
        }

        $texts = array_map(fn($item) => $item['text'] ?? '', $payload);

        $dimensions = (int) ($payload[0]['dims'] ?? 0);

        $response = $this->client->post('/v1/embed', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'texts' => $texts,
                'input_type' => $this->inputType->value,
                'embedding_types' => ['float'],
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        foreach ($data['embeddings']['float'] as $index => $embedding) {
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
        return $this->client->postAsync('/v1/embed', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'texts' => [$text],
                'input_type' => $this->inputType->value,
                'embedding_types' => ['float'],
            ]
        ]);
    }
}
