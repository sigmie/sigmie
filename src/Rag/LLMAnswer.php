<?php

declare(strict_types=1);

namespace Sigmie\Rag;

use DateTime;
use Sigmie\Document\Document;
use Sigmie\Mappings\Types\Date;

abstract class LLMAnswer
{
    public readonly string $timestamp;

    // TODO make readonly
    public string $conversationId;

    public function __construct(
        public readonly string $model,
        public readonly array $request,
        public readonly array $response,
    ) {
    }

    public function model(): string
    {
        return $this->model;
    }

    public function conversation(string $conversationId)
    {
        $this->conversationId = $conversationId;
    }

    public function totalTokens(): int
    {
        return $this->response['usage']['total_tokens'] ?? 0;
    }

    abstract public function __toString(): string;
}
