<?php

declare(strict_types=1);

namespace Sigmie\AI\LLMs;

use GuzzleHttp\Client;
use Sigmie\AI\Contracts\LLM;

class OpenAILLM implements LLM
{
    protected Client $client;
    protected string $apiKey;
    protected string $model;
    protected array $options = [];

    public function __construct(string $apiKey, string $model = 'gpt-4')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    public function answer(string $input, string $instructions, ?array $options = []): array
    {
        $messages = [
            ['role' => 'system', 'content' => $instructions],
            ['role' => 'user', 'content' => $input]
        ];

        return $this->chat($messages, $options);
    }

    public function chat(array $messages, ?array $options = []): array
    {
        $mergedOptions = array_merge($this->options, $options ?: []);
        
        $payload = array_merge([
            'model' => $this->model,
            'messages' => $messages,
        ], $mergedOptions);

        $response = $this->client->post('/v1/chat/completions', [
            'json' => $payload
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        return [
            'answer' => $data['choices'][0]['message']['content'],
            'usage' => $data['usage'] ?? null,
            'model' => $data['model'] ?? $this->model,
        ];
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function withOptions(array $options): self
    {
        $clone = clone $this;
        $clone->options = array_merge($this->options, $options);
        return $clone;
    }
}