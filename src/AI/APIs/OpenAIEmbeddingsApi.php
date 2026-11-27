<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\AI\OpenAIEmbeddingRequest;

class OpenAIEmbeddingsApi extends AbstractOpenAIApi implements EmbeddingsApi
{
    use OpenAIEmbeddingRequest {
        defaultDimensions as public;
        requestDimensions as public;
    }

    public function __construct(
        string $apiKey,
        string $model = 'text-embedding-3-small'
    ) {
        parent::__construct($apiKey, $model);
    }

    public function embed(string $text, int $dimensions): array
    {
        $response = $this->client->post('/v1/embeddings', [
            RequestOptions::JSON => $this->requestBody($text, $dimensions),
        ]);

        return ApiResponse::embeddings($response, 1)[0];
    }

    public function batchEmbed(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        foreach ($this->batchRequests($payload, $this->maxBatchSize()) as $group) {
            $response = $this->client->post('/v1/embeddings', [
                RequestOptions::JSON => $this->requestBody($group['texts'], $group['dims']),
            ]);

            foreach (ApiResponse::embeddings($response, count($group['keys'])) as $index => $vector) {
                $payload[$group['keys'][$index]]['vector'] = $vector;
            }
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): PromiseInterface
    {
        return $this->client->postAsync('/v1/embeddings', [
            RequestOptions::JSON => $this->requestBody($text, $dimensions),
        ]);
    }

    public function maxBatchSize(): int
    {
        return $this->batchInputLimit();
    }
}
