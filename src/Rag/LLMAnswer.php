<?php

declare(strict_types=1);

namespace Sigmie\Rag;

use Stringable;

abstract class LLMAnswer implements Stringable
{
    public readonly string $timestamp;

    // TODO make readonly
    public string $conversationId;

    public function __construct(
        public readonly string $model,
        public readonly array $request,
        public readonly array $response,
    ) {}

    public function model(): string
    {
        return $this->model;
    }

    public function conversation(string $conversationId): void
    {
        $this->conversationId = $conversationId;
    }

    public function totalTokens(): int
    {
        return $this->response['usage']['total_tokens'] ?? 0;
    }

    abstract public function __toString(): string;
}
