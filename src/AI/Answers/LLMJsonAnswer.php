<?php

declare(strict_types=1);

namespace Sigmie\AI\Answers;

class LLMJsonAnswer extends AbstractLLMAnswer
{
    public function __construct(
        string $model,
        array $request,
        array $response,
        protected array $jsonData
    ) {
        parent::__construct($model, $request, $response);
    }

    public function json(): array
    {
        return $this->jsonData;
    }

    public function __toString(): string
    {
        return (string) json_encode($this->jsonData);
    }
}
