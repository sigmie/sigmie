<?php

declare(strict_types=1);

namespace Sigmie\AI\Embedders;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Http\Promise\Promise;
use Sigmie\AI\Contracts\Embedder;

class VoyageProvider implements Embedder
{
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
            'json' => [
                'model' => $this->model,
                'input' => [$text],
                'output_dimension' => $dimensions,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['data'][0]['embedding'];
    }

    public function batchEmbed(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        $texts = array_column($payload, 'text');
        $dimensions = isset($payload[0]['dims']) ? (int) $payload[0]['dims'] : null;

        $requestData = [
            'model' => $this->model,
            'input' => $texts,
        ];

        if ($dimensions !== null) {
            $requestData['output_dimension'] = $dimensions;
        }

        $response = $this->client->post('/v1/embeddings', [
            'json' => $requestData,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        foreach ($data['data'] as $index => $embedding) {
            $payload[$index]['vector'] = $embedding['embedding'];
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): Promise
    {
        $promise = $this->client->postAsync('/v1/embeddings', [
            'json' => [
                'model' => $this->model,
                'input' => [$text],
                'output_dimension' => $dimensions,
            ],
        ]);

        return new class($promise) implements Promise
        {
            public function __construct(private PromiseInterface $promise) {}

            public function then(?callable $onFulfilled = null, ?callable $onRejected = null)
            {
                return $this->promise->then(
                    function ($response) use ($onFulfilled) {
                        $data = json_decode($response->getBody()->getContents(), true);
                        $embeddings = $data['data'][0]['embedding'];
                        if ($onFulfilled !== null) {
                            return $onFulfilled(['_embeddings' => $embeddings]);
                        }

                        return ['_embeddings' => $embeddings];
                    },
                    $onRejected
                );
            }

            public function getState()
            {
                return $this->promise->getState();
            }

            public function wait($unwrap = true)
            {
                $response = $this->promise->wait($unwrap);
                $data = json_decode($response->getBody()->getContents(), true);

                return ['_embeddings' => $data['data'][0]['embedding']];
            }
        };
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
