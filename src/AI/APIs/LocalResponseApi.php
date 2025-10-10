<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Sigmie\AI\Answers\LocalAnswer;
use Sigmie\AI\Contracts\LLMApi;
use Sigmie\AI\Contracts\LLMAnswer;
use Sigmie\AI\Prompt;
use Sigmie\Rag\LLMJsonAnswer;

class LocalResponseApi implements LLMApi
{
    protected Client $client;

    protected string $model;

    public function __construct(
        string $baseUrl = 'http://localhost:7999',
        string $model = 'microsoft/Phi-3-mini-4k-instruct'
    ) {
        $this->model = $model;
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 120,
        ]);
    }

    public function answer(Prompt $prompt): LLMAnswer
    {
        $messages = $this->convertMessages($prompt);

        $options = [
            RequestOptions::JSON => [
                'model' => $this->model,
                'messages' => $messages,
                'stream' => false,
                'temperature' => 0.7,
            ],
        ];

        $response = $this->client->post('/v1/chat/completions', $options);
        $data = json_decode($response->getBody()->getContents(), true);

        return new LocalAnswer(
            $this->model,
            $options[RequestOptions::JSON],
            $data,
        );
    }

    public function jsonAnswer(Prompt $prompt): LLMJsonAnswer
    {
        $messages = $this->convertMessages($prompt);
        $schema = $prompt->jsonSchema();

        $options = [
            RequestOptions::JSON => [
                'model' => $this->model,
                'messages' => $messages,
                'stream' => false,
                'temperature' => 0.7,
                'response_format' => [
                    'type' => 'json_object',
                ],
            ],
        ];

        // Add JSON schema instruction to system message
        $schemaInstruction = "You must respond with valid JSON matching this schema: " . json_encode($schema);
        $options[RequestOptions::JSON]['messages'][] = [
            'role' => 'system',
            'content' => $schemaInstruction
        ];

        $response = $this->client->post('/v1/chat/completions', $options);
        $data = json_decode($response->getBody()->getContents(), true);

        $content = $data['choices'][0]['message']['content'] ?? '{}';
        $jsonData = json_decode($content, true) ?? [];

        return new LLMJsonAnswer(
            $this->model,
            $options[RequestOptions::JSON],
            $data,
            $jsonData,
        );
    }

    public function streamAnswer(Prompt $prompt): iterable
    {
        $messages = $this->convertMessages($prompt);

        $options = [
            RequestOptions::JSON => [
                'model' => $this->model,
                'messages' => $messages,
                'stream' => true,
                'temperature' => 0.7,
            ],
            RequestOptions::STREAM => true,
        ];

        $response = $this->client->post('/v1/chat/completions', $options);

        yield from $this->processStreamResponse($response);
    }

    public function model(): string
    {
        return $this->model;
    }

    protected function convertMessages(Prompt $prompt): array
    {
        return array_map(fn($message) => [
            'role' => $message['role']->toOpenAI(),
            'content' => $message['content']
        ], $prompt->messages());
    }

    protected function processStreamResponse(ResponseInterface $response): iterable
    {
        $stream = $response->getBody();
        $buffer = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(256);
            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if (strpos($line, 'data: ') === 0) {
                    $data = substr($line, 6);

                    if (trim($data) === '[DONE]') {
                        continue;
                    }

                    $decoded = json_decode(trim($data), true);

                    if (isset($decoded['choices'][0]['delta']['content'])) {
                        yield $decoded['choices'][0]['delta']['content'];
                    }
                }
            }
        }

        if (!empty($buffer) && strpos($buffer, 'data: ') === 0) {
            $data = substr($buffer, 6);
            if (trim($data) !== '[DONE]') {
                $decoded = json_decode(trim($data), true);
                if (isset($decoded['choices'][0]['delta']['content'])) {
                    yield $decoded['choices'][0]['delta']['content'];
                }
            }
        }
    }
}
