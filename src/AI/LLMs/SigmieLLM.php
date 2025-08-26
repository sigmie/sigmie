<?php

declare(strict_types=1);

namespace Sigmie\AI\LLMs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\AI\Contracts\LLM;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;

class SigmieLLM implements LLM
{
    protected JSONClient $http;
    protected string $model = 'sigmie-llm';
    protected array $options = [];

    public function __construct()
    {
        $this->http = JSONClient::create([
            'https://ai-b.sigmie.app',
        ]);
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