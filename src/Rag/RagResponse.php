<?php

declare(strict_types=1);

namespace Sigmie\Rag;

use Sigmie\Document\Hit;

class RagResponse
{
    protected array $retrievedDocs = [];
    protected array $rerankedDocs = [];
    protected string $prompt = '';
    protected string $finalAnswer = '';
    protected bool $streaming = false;

    public function __construct(
        protected array $hits,
        protected ?array $rerankedHits = null,
        protected ?string $ragPrompt = null,
    ) {
        $this->retrievedDocs = $hits;
        $this->rerankedDocs = $rerankedHits ?? [];
        $this->prompt = $ragPrompt ?? '';
    }

    public function retrievedDocuments(): array
    {
        return $this->retrievedDocs;
    }

    public function rerankedDocuments(): array
    {
        return $this->rerankedDocs;
    }

    public function hasReranking(): bool
    {
        return !empty($this->rerankedDocs);
    }

    public function prompt(): string
    {
        return $this->prompt;
    }

    public function setFinalAnswer(string $answer): void
    {
        $this->finalAnswer = $answer;
    }

    public function finalAnswer(): string
    {
        return $this->finalAnswer;
    }

    public function context(): array
    {
        return [
            'retrieved_count' => count($this->retrievedDocs),
            'reranked_count' => count($this->rerankedDocs),
            'has_reranking' => $this->hasReranking(),
            'documents' => $this->hasReranking() ? $this->rerankedDocs : $this->retrievedDocs,
        ];
    }

    public function toArray(): array
    {
        return [
            'context' => $this->context(),
            'prompt' => $this->prompt,
            'answer' => $this->finalAnswer,
            'metadata' => [
                'retrieved_documents' => $this->retrievedDocs,
                'reranked_documents' => $this->rerankedDocs,
            ]
        ];
    }

    public function streamingChunk(string $delta, bool $done = false): array
    {
        return [
            'type' => $done ? 'done' : 'delta',
            'delta' => $delta,
            'done' => $done,
            'context' => $done ? $this->context() : null,
        ];
    }

    public function startStreamingChunk(): array
    {
        return [
            'type' => 'start',
            'delta' => '',
            'done' => false,
            'context' => $this->context(),
        ];
    }
}