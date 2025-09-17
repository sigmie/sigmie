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
            'type' => $done ? 'stream.complete' : 'content.delta',
            'delta' => $delta,
            'done' => $done,
            'context' => $done ? $this->context() : null,
        ];
    }

    public function startStreamingChunk(): array
    {
        return [
            'type' => 'stream.start',
            'delta' => '',
            'done' => false,
            'context' => $this->context(),
        ];
    }

    public function searchingEvent(): array
    {
        return [
            'type' => 'search.started',
            'message' => 'Searching for relevant documents...',
            'delta' => '',
            'done' => false,
            'context' => null,
        ];
    }

    public function searchCompleteEvent(int $count): array
    {
        return [
            'type' => 'search.completed',
            'message' => "Found {$count} relevant documents",
            'delta' => '',
            'done' => false,
            'context' => null,
            'metadata' => [
                'document_count' => $count,
            ],
        ];
    }

    public function rerankingEvent(): array
    {
        return [
            'type' => 'rerank.started',
            'message' => 'Reranking documents for relevance...',
            'delta' => '',
            'done' => false,
            'context' => null,
        ];
    }

    public function rerankCompleteEvent(int $originalCount, int $rerankedCount): array
    {
        return [
            'type' => 'rerank.completed',
            'message' => "Reranked {$originalCount} documents to top {$rerankedCount}",
            'delta' => '',
            'done' => false,
            'context' => null,
            'metadata' => [
                'original_count' => $originalCount,
                'reranked_count' => $rerankedCount,
            ],
        ];
    }

    public function promptGenerationEvent(): array
    {
        return [
            'type' => 'prompt.generated',
            'message' => 'Generated RAG prompt with context',
            'delta' => '',
            'done' => false,
            'context' => null,
        ];
    }

    public function llmRequestEvent(): array
    {
        return [
            'type' => 'llm.request.started',
            'message' => 'Generating response...',
            'delta' => '',
            'done' => false,
            'context' => null,
        ];
    }

    public function llmFirstTokenEvent(): array
    {
        return [
            'type' => 'llm.first_token',
            'message' => 'Response stream started',
            'delta' => '',
            'done' => false,
            'context' => null,
        ];
    }
}