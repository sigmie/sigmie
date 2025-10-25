<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\EmbeddingsApi;

class OpenAIEmbeddingsApi extends AbstractOpenAIApi implements EmbeddingsApi
{
    public function __construct(
        string $apiKey,
        string $model = 'text-embedding-3-small'
    ) {
        parent::__construct($apiKey, $model);
    }

    public function embed(string $text, int $dimensions): array
    {
        $response = $this->client->post('/v1/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $text,
                'dimensions' => $dimensions
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        return $data['data'][0]['embedding'];
    }

    public function batchEmbed(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        $texts = array_map(fn($item) => $item['text'] ?? '', $payload);

        $dimensions = $payload[0]['dims'] ?? 1536;

        $response = $this->client->post('/v1/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $texts,
                'dimensions' => (int) $dimensions
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
        return $this->client->postAsync('/v1/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $text,
                'dimensions' => $dimensions
            ]
        ]);
    }
}
