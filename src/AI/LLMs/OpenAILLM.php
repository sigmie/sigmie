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
        $response = $this->client->post('/v1/responses', [
            RequestOptions::JSON => [
                'model' => $this->llmModel,
                'input' => $input,
                'instructions' => $instructions,
                'stream' => $stream,
                // 'text' => [
                //     'format' => [
                //         'name' => 'rag_answer',
                //         'type' => 'json_schema',
                //         'schema' => [
                //             'type' => 'object',
                //             'required' => ['answer', 'citations'],
                //             'properties' => [
                //                 'answer' => ['type' => 'string'],
                //                 'citations' => [
                //                     'type' => 'array',
                //                     'items' => ['type' => 'string']
                //                 ],
                //             ],
                //             'additionalProperties' => false,
                //         ],
                //         'strict' => true
                //     ]
                // ],
            ],
        ]);

        if ($stream) {
            return $this->streamAnswer($response);
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    private function streamAnswer(ResponseInterface $response): iterable
    {
        $stream = $response->getBody();
        $buffer = '';

        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);

            // Process complete SSE lines
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
                        yield $decoded['delta'];
                    }
                }
            }
        }
    }
}
