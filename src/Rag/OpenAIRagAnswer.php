<?php

declare(strict_types=1);

namespace Sigmie\Rag;

class OpenAIRagAnswer extends LLMAnswer
{
    public function __construct(
        string $model,
        array $request,
        array $response,
    ) {
        parent::__construct($model, $request, $response);
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
