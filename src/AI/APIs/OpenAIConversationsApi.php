<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Sigmie\AI\Answers\OpenAIConversationAnswer;
use Sigmie\AI\Contracts\LLMApi;
use Sigmie\AI\Prompt;
use Sigmie\AI\Contracts\LLMAnswer;

class OpenAIConversationsApi extends AbstractOpenAIApi implements LLMApi
{
    protected ?string $conversationId = null;
    protected bool $autoCleanup = true;
    protected array $metadata = [];

    public function __construct(
        string $apiKey,
        ?string $conversationId = null,
        array $metadata = [],
        string $model = 'gpt-5-nano',
    ) {

        parent::__construct($apiKey, $model);

        $this->conversationId = $conversationId;
        $this->metadata = $metadata;
    }

    protected function createConversation(string $input): string
    {
        // Create conversation with initial message
        $conversationPayload = [
            //['metadata' => $this->metadata],
            'items' => [
                [
                    'type' => 'message',
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $input]
                    ]
                ]
            ]
        ];

        $conversationResponse = $this->client->post('/v1/conversations', [
            RequestOptions::JSON => $conversationPayload,
        ]);

        $conversation = json_decode($conversationResponse->getBody()->getContents(), true);

        return $conversation['id'];
    }

    public function metadata(): array
    {
        return [
            'conversation' => $this->conversation(),
            'model' => $this->model,
        ];
    }

    public function conversation(): string
    {
        return $this->conversationId ?? $this->createConversation('Hello!');
    }

    public function answer(Prompt $prompt): LLMAnswer
    {
        $conversation = $this->conversation();

        $input = array_map(function ($message) {
            return [
                'role' => $message['role']->value,
                'content' => $message['content']
            ];
        }, $prompt->messages());

        $options = [
            RequestOptions::JSON => [
                'conversation' => $conversation,
                'model' => $this->model,
                'input' => $input,
                'stream' => false,
            ],
        ];

        $response = $this->client->post('/v1/responses', $options);

        $data = json_decode($response->getBody()->getContents(), true);

        return new OpenAIConversationAnswer($this->model, $options['json'], $data, $conversation);
    }

    public function streamAnswer(Prompt $prompt): iterable
    {
        $conversation = $this->conversation();

        yield ['type' => 'conversation.created', 'conversation_id' => $conversation];

        $input = array_map(function ($message) {
            return [
                'role' => $message['role']->value,
                'content' => $message['content']
            ];
        }, $prompt->messages());

        $options = [
            RequestOptions::JSON => [
                'conversation' => $conversation,
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

                if (strpos($line, 'data: ') === 0) {
                    $data = substr($line, 6);

                    // Skip the [DONE] message
                    if (trim($data) === '[DONE]') {
                        continue;
                    }

                    $decoded = json_decode(trim($data), true);

                    // Handle Response API streaming format
                    if (isset($decoded['type']) && $decoded['type'] === 'response.output_text.delta') {
                        yield $decoded['delta'];
                    }
                    // Also handle Conversations API streaming response format
                    elseif (isset($decoded['delta']['content'])) {
                        yield $decoded['delta']['content'];
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
                } elseif (isset($decoded['delta']['content'])) {
                    yield $decoded['delta']['content'];
                }
            }
        }
    }
}
