<?php

declare(strict_types=1);

namespace Sigmie\AI\LLMs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Sigmie\AI\Contracts\Embedder;
use Sigmie\AI\Contracts\LLM;

class OpenAILLM implements LLM, Embedder
{
    protected Client $client;

    public function __construct(
        string $apiKey,
        protected string $embeddingModel = 'text-embedding-3-small',
        protected string $llmModel = 'gpt-5-nano',
    ) {
        $this->llmModel = $llmModel;

        $this->client = new Client([
            'base_uri' => 'https://api.openai.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
        ]);
    }

    public function embed(string $text, int $dimensions): array
    {
        $response = $this->client->post('/v1/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->embeddingModel,
                'input' => $text,
                'dimensions' => $dimensions
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

        $texts = array_map(function ($item) {
            return $item['text'] ?? '';
        }, $payload);

        $dimensions = $payload[0]['dims'] ?? 1536;

        $response = $this->client->post('/v1/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->embeddingModel,
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

    public function promiseEmbed(string $text, int $dimensions)
    {
        return $this->client->postAsync('/v1/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->embeddingModel,
                'input' => $text,
                'dimensions' => $dimensions
            ]
        ]);
    }

    public function answer(string $input, string $instructions, bool $stream = false): iterable
    {
        $options = [
            RequestOptions::JSON => [
                'model' => $this->llmModel,
                'input' => $input,
                'instructions' => $instructions,
                'stream' => $stream,
            ],
        ];

        // Add stream option for Guzzle when streaming
        if ($stream) {
            $options[RequestOptions::STREAM] = true;
        }

        $response = $this->client->post('/v1/responses', $options);

        if ($stream) {
            // Return generator for direct streaming
            yield from $this->streamAnswer($response);
        } else {
            // Return array wrapped in generator for consistency
            $data = json_decode($response->getBody()->getContents(), true);
            yield $data;
        }
    }

    private function streamAnswer(ResponseInterface $response): iterable
    {
        $stream = $response->getBody();
        $buffer = '';

        while (!$stream->eof()) {
            // Read smaller chunks for faster yielding
            $chunk = $stream->read(256);
            if ($chunk === '') {
                continue;
            }
            
            $buffer .= $chunk;

            // Process complete SSE lines immediately
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if (strpos($line, 'data: ') === 0) {
                    $data = substr($line, 6);

                    // Skip the [DONE] message
                    if (trim($data) === '[DONE]') {
                        continue;
                    }

                    $decoded = json_decode(trim($data), true);

                    if (isset($decoded['type']) && $decoded['type'] === 'response.output_text.delta') {
                        // Yield immediately without buffering
                        yield $decoded['delta'];
                    }
                }
            }
        }
        
        // Process any remaining buffer
        if (!empty($buffer) && strpos($buffer, 'data: ') === 0) {
            $data = substr($buffer, 6);
            if (trim($data) !== '[DONE]') {
                $decoded = json_decode(trim($data), true);
                if (isset($decoded['type']) && $decoded['type'] === 'response.output_text.delta') {
                    yield $decoded['delta'];
                }
            }
        }
    }
}
