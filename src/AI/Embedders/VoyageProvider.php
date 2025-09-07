<?php

declare(strict_types=1);

namespace Sigmie\AI\Embedders;

use Http\Promise\Promise;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Sigmie\AI\Contracts\Embedder;

class VoyageProvider implements Embedder
{
    protected Client $client;
    protected string $apiKey;
    protected string $model;

    public function __construct(string $apiKey, string $model = 'voyage-3')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->client = new Client([
            'base_uri' => 'https://api.voyageai.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    public function embed(string $text, int $dimensions): array
    {
        $response = $this->client->post('/v1/embeddings', [
            'json' => [
                'model' => $this->model,
                'input' => [$text],
                'output_dimension' => $dimensions,
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

        $texts = array_column($payload, 'text');
        $dimensions = isset($payload[0]['dims']) ? (int)$payload[0]['dims'] : null;

        $requestData = [
            'model' => $this->model,
            'input' => $texts,
        ];

        if ($dimensions !== null) {
            $requestData['output_dimension'] = $dimensions;
        }

        $response = $this->client->post('/v1/embeddings', [
            'json' => $requestData
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
            ]
        ]);

        return new class($promise) implements Promise {
            private PromiseInterface $promise;

            public function __construct(PromiseInterface $promise)
            {
                $this->promise = $promise;
            }

            public function then(callable $onFulfilled = null, callable $onRejected = null)
            {
                return $this->promise->then(
                    function ($response) use ($onFulfilled) {
                        $data = json_decode($response->getBody()->getContents(), true);
                        $embeddings = $data['data'][0]['embedding'];
                        if ($onFulfilled) {
                            return $onFulfilled(['embeddings' => $embeddings]);
                        }
                        return ['embeddings' => $embeddings];
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
                return ['embeddings' => $data['data'][0]['embedding']];
            }
        };
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
