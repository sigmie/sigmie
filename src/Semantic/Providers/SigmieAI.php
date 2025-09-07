<?php

declare(strict_types=1);

namespace Sigmie\Semantic\Providers;

use Http\Promise\Promise;
use GuzzleHttp\Psr7\Uri;
use Sigmie\AI\Contracts\Embedder;
use Sigmie\AI\Contracts\LLM;
use Sigmie\AI\Contracts\Reranker;
use Sigmie\Document\Hit;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;

class SigmieAI implements Embedder, LLM, Reranker
{
    protected JSONClient $http;
    protected string $model = 'sigmie-all';
    protected array $options = [];

    public function __construct()
    {
        $this->http = JSONClient::create([
            // 'https://ai-a.sigmie.app',
            'https://ai-b.sigmie.app',
            // 'https://ai-c.sigmie.app',
        ]);
    }

    public function formatHit(Hit $hit): string {
        return json_encode($hit->_source);
    }

    public function rerank(array $documents, string $queryString, ?int $topK = null): array
    {
        $payload = [
            'documents' => $documents,
            'query' => $queryString,
        ];

        if (count($documents) === 0) {
            return [];
        }

        $response = $this->http->request(
            new JSONRequest(
                'POST',
                new Uri('/rerank'),
                $payload
            )
        );

        $scores = $response->json('reranked_scores');

        return $scores;
    }

    public function batchEmbed(array $payload): array
    {
        if (count($payload) === 0) {
            return [];
        }

        $response = $this->http->request(new JSONRequest(
            'POST',
            new Uri('/embeddings'),
            $payload
        ));

        foreach ($response->json() as $index => $result) {
            $embeddings[] = [
                'embeddings' => dot($result)->get('embeddings'),
                dot($result)->get('embeddings')
            ];

            $payload[$index]['vector'] = dot($result)->get('embeddings');
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): Promise
    {
        return $this->http->promise(new JSONRequest(
            'POST',
            new Uri('/embeddings'),
            [
                'text' => $text,
                'dims' => (string) $dimensions
            ]
        ));
    }

    public function embed(string $text, int $dimensions): array
    {
        $response = $this->http->request(new JSONRequest(
            'POST',
            new Uri('/embeddings'),
            [
                [
                    'text' => $text,
                    'dims' => (string) $dimensions
                ]
            ]
        ));

        return $response->json('0.embeddings');
    }

    public function answer(string $input, string $instructions, ?array $options = []): array
    {
        $response = $this->http->request(new JSONRequest(
            'POST',
            new Uri('/answer'),
            array_merge([
                'input' => $input,
                'instructions' => $instructions
            ], $options ?: $this->options)
        ));

        $data = $response->json();

        // Transform the response to ensure it has an 'answer' key
        // Check if the response has an 'output' field (SigmieAI format)
        if (isset($data['output'])) {
            // Extract the actual answer from the output
            $message = array_filter($data['output'], function ($msg) {
                return isset($msg['type']) && $msg['type'] === 'message';
            });

            if (!empty($message)) {
                $message = array_values($message)[0];
                if (isset($message['content'][0]['text'])) {
                    $text = $message['content'][0]['text'];
                    // Try to decode JSON if it looks like JSON
                    $decoded = json_decode($text, true);
                    if ($decoded !== null) {
                        return $decoded;
                    }
                    return ['answer' => $text];
                }
            }
        }

        // If there's already an 'answer' key, return as-is
        if (isset($data['answer'])) {
            return $data;
        }

        // Otherwise, wrap the response in an 'answer' key
        return ['answer' => $data];
    }

    public function chat(array $messages, ?array $options = []): array
    {
        $response = $this->http->request(new JSONRequest(
            'POST',
            new Uri('/chat'),
            array_merge([
                'messages' => $messages
            ], $options ?: $this->options)
        ));

        return $response->json();
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
