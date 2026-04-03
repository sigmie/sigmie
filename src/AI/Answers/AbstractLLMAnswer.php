<?php

declare(strict_types=1);

namespace Sigmie\AI\Answers;

use Sigmie\AI\Contracts\LLMAnswer as LLMAnswerContract;
use Stringable;

abstract class AbstractLLMAnswer implements LLMAnswerContract, Stringable
{
    public function __construct(
        public readonly string $model,
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

    abstract public function __toString(): string;
}
