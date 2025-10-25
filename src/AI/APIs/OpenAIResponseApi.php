<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Sigmie\AI\Answers\OpenAIAnswer;
use Sigmie\AI\Contracts\LLMApi;
use Sigmie\AI\Prompt;
use Sigmie\Rag\LLMJsonAnswer;
use Sigmie\AI\Contracts\LLMAnswer;

class OpenAIResponseApi extends AbstractOpenAIApi implements LLMApi
{
    public function __construct(
        string $apiKey,
        string $model = 'gpt-5-nano'
    ) {
        parent::__construct($apiKey, $model);
    }

    public function answer(Prompt $prompt): LLMAnswer
    {
        $input = array_map(fn($message): array => [
            'role' => $message['role']->toOpenAI(),
            'content' => $message['content']
        ], $prompt->messages());

        $options = [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $input,
                'stream' => false,
            ],
        ];

        $response = $this->client->post('/v1/responses', $options);

        $data = json_decode($response->getBody()->getContents(), true);

        return new OpenAIAnswer(
            $this->model,
            $options['json'],
            $data,
        );
    }

    public function jsonAnswer(Prompt $prompt): LLMJsonAnswer
    {
        $input = array_map(fn($message): array => [
            'role' => $message['role']->toOpenAI(),
            'content' => $message['content']
        ], $prompt->messages());

        $options = [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $input,
                'stream' => false,
                'text' => [
                    'format' => [
                        'name' => 'rag_answer',
                        'type' => 'json_schema',
                        'schema' => $prompt->jsonSchema(),
                    ],
                ]
            ],
        ];

        $response = $this->client->post('/v1/responses', $options);

        $data = json_decode($response->getBody()->getContents(), true);

        // Find the message output in the response array
        $messageOutput = array_filter($data['output'], fn($output): bool => $output['type'] === 'message');
        $messageOutput = array_values($messageOutput)[0];

        $content = $messageOutput['content'][0]['text'] ?? throw new \RuntimeException('No text content in response');

        return new LLMJsonAnswer(
            $this->model,
            $options['json'],
            $data,
            json_decode($content, true),
        );
    }

    public function streamAnswer(Prompt $prompt): iterable
    {
        $input = array_map(fn($message): array => [
            'role' => $message['role']->toOpenAI(),
            'content' => $message['content']
        ], $prompt->messages());

        $options = [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $input,
                'stream' => true,
            ],
            RequestOptions::STREAM => true,
        ];

        $response = $this->client->post('/v1/responses', $options);

        // Return generator for direct streaming
        yield from $this->processStreamResponse($response);
    }

    private function processStreamResponse(ResponseInterface $response): iterable
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

                if (str_starts_with($line, 'data: ')) {
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
        if ($buffer !== '' && $buffer !== '0' && str_starts_with($buffer, 'data: ')) {
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
