<?php

declare(strict_types=1);

namespace Sigmie\AI\Answers;

use Sigmie\AI\Contracts\LLMAnswer as LLMAnswerInterface;

class OpenAIAnswer implements LLMAnswerInterface
{
    public function __construct(
        protected string $model,
        public readonly array $request,
        public readonly array $response,
    ) {}

    public function model(): string
    {
        return $this->model;
    }

    public function totalTokens(): int
    {
        return $this->response['usage']['total_tokens'] ?? 0;
    }

    public function __toString(): string
    {
        // Extract the output text from the response
        $outputText = '';

        // The Response API returns output as an array with message objects
        if (isset($this->response['output']) && is_array($this->response['output'])) {
            foreach ($this->response['output'] as $outputItem) {
                // Find the message type output
                if (
                    isset($outputItem['type']) && $outputItem['type'] === 'message' &&
                    isset($outputItem['content'][0]['type']) && $outputItem['content'][0]['type'] === 'output_text' &&
                    isset($outputItem['content'][0]['text'])
                ) {
                    $outputText .= $outputItem['content'][0]['text'];
                }
            }
        }

        return $outputText;
    }
}
