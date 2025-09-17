<?php

declare(strict_types=1);

namespace Sigmie\AI\LLMs;

use GuzzleHttp\Psr7\Uri;
use Http\Promise\Promise;
use Sigmie\AI\Contracts\Embedder;
use Sigmie\AI\Contracts\LLM;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;
use Sigmie\Http\JSONResponse;

class OpenAILLM implements LLM, Embedder
{
    protected JSONClient $http;

    public function __construct(string $apiKey)
    {
        $this->http = JSONClient::createWithToken(
            hosts: ['https://api.openai.com'],
            token: $apiKey
        );
    }

    public function embed(string $text, int $dimensions): array
    {
        $payload = [
            'model' => 'text-embedding-3-small',
            'input' => $text,
            'dimensions' => $dimensions
        ];

        $request = new JSONRequest(
            'POST',
            new Uri('/v1/embeddings'),
            $payload
        );

        /** @var JSONResponse $response */
        $response = $this->http->request($request);

        return $response->json('data.0.embedding');
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

        $request = new JSONRequest(
            'POST',
            new Uri('/v1/embeddings'),
            [
                'model' => 'text-embedding-3-small',
                'input' => $texts,
                'dimensions' => (int) $dimensions
            ]
        );

        $response = $this->http->request($request);

        /** @var JSONResponse $response */

        foreach ($response->json('data') as $index => $result) {
            $payload[$index]['vector'] = $result['embedding'];
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): Promise
    {
        $request = new JSONRequest(
            'POST',
            new Uri('/v1/embeddings'),
            [
                'model' => 'text-embedding-3-small',
                'input' => $text,
                'dimensions' => $dimensions
            ]
        );

        return $this->http->promise($request);
    }

    public function answer(string $input, string $instructions, int $maxTokens, float $temperature): array
    {
        $request = new JSONRequest(
            'POST',
            new Uri('/v1/responses'),
            [
                'model' => 'gpt-5-nano',
                'input' => $input,
                'instructions' => $instructions,
                'stream'=> true,
                'text' => [
                    'format' => [
                        'name' => 'rag_answer',
                        'type' => 'json_schema',
                        'schema' => [
                            'type' => 'object',
                            'required' => ['answer', 'citations'],
                            'properties' => [
                                'answer' => ['type' => 'string'],
                                'citations' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string']
                                ],
                            ],
                            'additionalProperties' => false,
                        ],
                        'strict' => true
                    ]
                ],
            ],
        );

        $response = $this->http->request($request);

        return $response->json();
    }

    public function streamAnswer(string $input, string $instructions, int $maxTokens, float $temperature): iterable {

    }
}
