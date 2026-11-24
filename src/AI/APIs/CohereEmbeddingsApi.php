<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Enums\CohereInputType;

use function Sigmie\Functions\batch_slots;

class CohereEmbeddingsApi implements EmbeddingsApi
{
    protected Client $client;

    public function __construct(
        string $apiKey,
        protected CohereInputType $inputType,
        protected string $model = 'embed-english-v3.0'
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.cohere.ai',
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
        ]);
    }

    public function forSearchQueries(): static
    {
        $api = clone $this;
        if ($api->inputType === CohereInputType::SearchDocument) {
            $api->inputType = CohereInputType::SearchQuery;
        }

        return $api;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function embed(string $text, int $dimensions): array
    {
        $response = $this->client->post($this->embedEndpoint(), [
            RequestOptions::JSON => $this->requestBody([$text], $dimensions),
        ]);

        return ApiResponse::resizeEmbedding(ApiResponse::cohereEmbeddings($response, 1)[0], $dimensions);
    }

    public function batchEmbed(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        foreach (batch_slots($payload, null, $this->maxBatchSize()) as $group) {
            $dimensions = $group['dims'];
            $response = $this->client->post('/v2/embed', [
                RequestOptions::JSON => $this->requestBody($group['texts'], $dimensions),
            ]);

            foreach (ApiResponse::cohereEmbeddings($response, count($group['keys'])) as $index => $vector) {
                $payload[$group['keys'][$index]]['vector'] = ApiResponse::resizeEmbedding($vector, $dimensions);
            }
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): PromiseInterface
    {
        return $this->client->postAsync($this->embedEndpoint(), [
            RequestOptions::JSON => $this->requestBody([$text], $dimensions),
        ]);
    }

    private function embedEndpoint(): string
    {
        return $this->supportedDimensions() === [] ? '/v1/embed' : '/v2/embed';
    }

    public function supportedDimensions(): array
    {
        return match ($this->model) {
            'embed-v4.0' => [256, 512, 1024, 1536],
            'embed-v5.0-fast', 'embed-v5.0-pro' => [256, 512, 768, 1024, 1536, 2048],
            default => [],
        };
    }

    private function requestBody(array $texts, ?int $dimensions): array
    {
        $body = [
            'model' => $this->model,
            'texts' => $texts,
            'input_type' => $this->inputType->value,
            'embedding_types' => ['float'],
        ];

        if (in_array($dimensions, $this->supportedDimensions(), true)) {
            $body['output_dimension'] = $dimensions;
        }

        return $body;
    }

    public function maxBatchSize(): int
    {
        return 96;
    }
}
