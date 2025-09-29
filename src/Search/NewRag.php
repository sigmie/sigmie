<?php

declare(strict_types=1);

namespace Sigmie\Search;

use RuntimeException;
use Sigmie\AI\Contracts\LLMApi;
use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Rag\NewRerank;
use Sigmie\Rag\RagResponse;
use Sigmie\Search\Contracts\MultiSearchResponse;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\AI\Contracts\LLMAnswer;
use Sigmie\AI\History\Index as HistoryIndex;

class NewRag
{
    protected null|NewMultiSearch|NewSearch $searchBuilder = null;

    protected ?NewRerank $rerankBuilder = null;

    protected ?\Closure $promptBuilder = null;

    protected string $instructions = '';

    protected string $conversationId = '';

    protected string $userToken = '';

    protected ?HistoryIndex $historyIndex = null;

    public function __construct(
        protected LLMApi $llm,
        protected ?RerankApi $reranker = null
    ) {}

    public function search(NewMultiSearch|NewSearch $builder): self
    {
        $this->searchBuilder = $builder;

        return $this;
    }

    public function rerank(\Closure $callback): self
    {
        $this->rerankBuilder = new NewRerank($this->reranker);

        $callback($this->rerankBuilder);

        return $this;
    }

    /**
     * Configure the prompt builder
     * @param \Closure $callback Callback that receives NewRagPrompt
     */
    public function prompt(\Closure $callback): self
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Prompt callback must be callable');
        }

        $this->promptBuilder = $callback;

        return $this;
    }

    public function historyIndex(HistoryIndex $index)
    {
        $this->historyIndex = $index;

        return $this;
    }

    public function conversationId(string $conversationId)
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    public function userToken(string $userToken)
    {
        $this->userToken = $userToken;

        return $this;
    }

    /**
     * Get answer without streaming (returns complete response)
     */
    public function answer(): LLMAnswer
    {
        // Execute search
        if (!$this->searchBuilder) {
            throw new RuntimeException('Search must be configured before calling answer()');
        }

        if ($this->searchBuilder instanceof NewSearch) {
            $multiSearch = new NewMultiSearch(
                $this->searchBuilder->elasticsearchConnection,
                $this->searchBuilder->embeddingsApi
            );

            $multiSearch->add($this->searchBuilder, name: 'documents');

            $this->searchBuilder = $multiSearch;
        }

        if ($this->historyIndex) {
            $this->searchBuilder->add(
                $this->historyIndex->search(
                    $this->conversationId ?? prefix_id('conv', 10),
                    $this->userToken
                ),
                name: 'history'
            );
        }

        // Get all hits grouped by name in a single HTTP call
        $groupedHits = $this->searchBuilder->groupedHits();
        $documentHits = $groupedHits['documents'] ?? [];
        $historyHits = $groupedHits['history'] ?? [];

        ray($historyHits);

        // For now, use document hits as primary hits
        $hits = $documentHits;

        // Apply reranking if configured
        if ($this->reranker && $this->rerankBuilder) {
            $hits = $this->rerankBuilder->rerank($hits);
        }

        $prompt = new NewRagPrompt($hits);

        if ($this->promptBuilder) {
            ($this->promptBuilder)($prompt);
        }

        $answer = $this->llm->answer($prompt);

        return $answer;
    }

    /**
     * Stream answer with real-time events and chunks
     */
    public function streamAnswer(): iterable
    {
        // Emit search_start event
        yield ['type' => 'search_start', 'timestamp' => microtime(true)];

        // Execute search
        if (!$this->searchBuilder) {
            throw new RuntimeException('Search must be configured before calling streamAnswer()');
        }

        if ($this->searchBuilder instanceof NewSearch) {
            $multiSearch = new NewMultiSearch(
                $this->searchBuilder->elasticsearchConnection,
                $this->searchBuilder->embeddingsApi
            );

            $multiSearch->add($this->searchBuilder, name: 'documents');

            $this->searchBuilder = $multiSearch;
        }

        if ($this->historyIndex) {
            $this->searchBuilder->add($this->historyIndex->newSearch(), name: 'history');
        }

        // Get all hits grouped by name in a single HTTP call
        $groupedHits = $this->searchBuilder->groupedHits();
        $documentHits = $groupedHits['documents'] ?? [];
        $historyHits = $groupedHits['history'] ?? [];

        // For now, use document hits as primary hits
        $hits = $documentHits;

        // Emit search_complete event with hit count
        yield ['type' => 'search_complete', 'hits' => count($hits), 'timestamp' => microtime(true)];

        // Apply reranking if configured
        if ($this->reranker && $this->rerankBuilder) {
            yield ['type' => 'rerank_start', 'timestamp' => microtime(true)];

            $hits = $this->rerankBuilder->rerank($hits);

            yield ['type' => 'rerank_complete', 'hits' => count($hits), 'timestamp' => microtime(true)];
        }

        // Build prompt
        yield ['type' => 'prompt_start', 'timestamp' => microtime(true)];

        $prompt = new NewRagPrompt($hits);

        if ($this->promptBuilder) {
            ($this->promptBuilder)($prompt);
        }

        yield ['type' => 'prompt_complete', 'timestamp' => microtime(true)];

        // Start LLM streaming
        yield ['type' => 'llm_start', 'timestamp' => microtime(true)];

        // Stream directly from OpenAI API response
        foreach ($this->llm->streamAnswer($prompt) as $chunk) {
            yield ['type' => 'llm_chunk', 'content' => $chunk, 'timestamp' => microtime(true)];
        }

        // Emit completion event
        yield ['type' => 'llm_complete', 'timestamp' => microtime(true)];
    }
}
