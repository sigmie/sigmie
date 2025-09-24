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

    public function __toString(): string
    {
        // Extract the output text from the response
        $outputText = '';

        // The Response API returns output as an array with message objects
        if (isset($data['output']) && is_array($data['output'])) {
            foreach ($data['output'] as $outputItem) {
                // Find the message type output
                if (
                    isset($outputItem['type']) && $outputItem['type'] === 'message' &&
                    isset($outputItem['content'][0]['text'])
                ) {
                    $outputText .= $outputItem['content'][0]['text'];
                }
            }
        }

        return $outputText;
    }
}
