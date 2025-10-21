<?php

declare(strict_types=1);

namespace Sigmie\Rag;

use Sigmie\AI\Contracts\LLMAnswer;

class RagAnswer
{
    /**
     * @param array<\Sigmie\Document\Hit> $hits
     */
    public function __construct(
        public readonly array $hits,
        public readonly LLMAnswer|LLMJsonAnswer $llmAnswear,
        public readonly ?string $conversationId = null,
    ) {}

    public function __toString(): string
    {
        return $this->llmAnswear->__toString();
    }

    public function totalTokens(): int
    {
        return $this->llmAnswear->totalTokens();
    }

    public function model(): string
    {
        return $this->llmAnswear->model();
    }
}
