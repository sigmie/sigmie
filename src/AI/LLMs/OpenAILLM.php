<?php

declare(strict_types=1);

namespace Sigmie\AI\LLMs;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise as PromisePromise;
use Http\Promise\Promise;
use Sigmie\AI\Contracts\Embedder;
use Sigmie\AI\Contracts\LLM;

class OpenAILLM implements LLM, Embedder
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

    public function embed(string $text, int $dimensions): array
    {
        $payload = [
            'model' => 'text-embedding-3-small',
            'input' => $text,
            'dimensions' => $dimensions
        ];

        $response = $this->client->post('/v1/embeddings', [
            'json' => $payload
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['data'][0]['embedding'];
    }

    public function batchEmbed(array $payload): array
    {
        if (count($payload) === 0) {
            return [];
        }

        $texts = array_map(function($item) {
            return $item['text'] ?? '';
        }, $payload);

        $dimensions = $payload[0]['dims'] ?? 1536;

        $response = $this->client->post('/v1/embeddings', [
            'json' => [
                'model' => 'text-embedding-3-small',
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

    public function promiseEmbed(string $text, int $dimensions): PromisePromise 
    {
        return $this->client->postAsync('/v1/embeddings', [
            'json' => [
                'model' => 'text-embedding-3-small',
                'input' => $text,
                'dimensions' => $dimensions
            ]
        ]);
    }

    public function answer(string $input, string $instructions, int $maxTokens, float $temperature): array
    {
        $messages = [
            ['role' => 'system', 'content' => $instructions],
            ['role' => 'user', 'content' => $input]
        ];

        return $this->chat($messages, [
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ]);
    }

    public function streamAnswer(string $input, string $instructions, int $maxTokens, float $temperature): iterable
    {
        $messages = [
            ['role' => 'system', 'content' => $instructions],
            ['role' => 'user', 'content' => $input]
        ];

        return $this->streamChat($messages, [
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ]);
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

    public function streamChat(array $messages, ?array $options = []): iterable
    {
        $mergedOptions = array_merge($this->options, $options ?: []);

        $payload = array_merge([
            'model' => $this->model,
            'messages' => $messages,
            'stream' => true,
        ], $mergedOptions);

        $response = $this->client->post('/v1/chat/completions', [
            'json' => $payload,
            'stream' => true,
        ]);

        $body = $response->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $chunk = $body->read(1024);
            $buffer .= $chunk;
            
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines);
            
            foreach ($lines as $line) {
                if (trim($line) === '' || $line === 'data: [DONE]') {
                    continue;
                }
                
                if (strpos($line, 'data: ') === 0) {
                    $json = substr($line, 6);
                    $data = json_decode($json, true);
                    
                    if (isset($data['choices'][0]['delta']['content'])) {
                        yield [
                            'content' => $data['choices'][0]['delta']['content'],
                            'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
                        ];
                    }
                }
            }
        }
    }
}
