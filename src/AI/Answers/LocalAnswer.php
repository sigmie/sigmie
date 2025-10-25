<?php

declare(strict_types=1);

namespace Sigmie\AI\Answers;

use Sigmie\AI\Contracts\LLMAnswer as LLMAnswerInterface;

class LocalAnswer implements LLMAnswerInterface
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
        return (string) ($this->response['choices'][0]['message']['content'] ?? '');
    }
}
