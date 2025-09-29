<?php

declare(strict_types=1);

namespace Sigmie\Rag;

use Sigmie\Document\Document;

abstract class LLMAnswer
{
    public readonly string $conversationId;
    public readonly string $userToken;
    public readonly string $timestamp;
    public readonly string $instructions;
    public readonly string $summary;
    public readonly array $tags;
    public readonly array $turns;

    public function __construct(
        public readonly string $model,
        protected array $request,
        protected array $response,
    ) {}

    public function model(): string
    {
        return $this->model;
    }

    abstract public function __toString(): string;

    public function toDocument(): Document
    {
        $source = [
            'conversationId' => $this->conversationId,
            'userToken' => $this->userToken,
            'timestamp' => $this->timestamp,
            'instructions' => $this->instructions,
            'summary' => $this->summary,
            'tags' => $this->tags,
            'turns' => $this->turns,
            'model' => $this->model,
            'request' => $this->request,
            'response' => $this->response,
        ];

        return new Document($source);
    }
}
