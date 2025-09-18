<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Sigmie\AI\Contracts\LLMApi;

class OpenAIResponseApi extends AbstractOpenAIApi implements LLMApi
{
    public function __construct(
        string $apiKey,
        string $model = 'gpt-5-nano'
    ) {
        parent::__construct($apiKey, $model);
    }

    public function answer(string $input, string $instructions, bool $stream = false): iterable
    {
        $options = [
            RequestOptions::JSON => [
                'model' => $this->model,
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