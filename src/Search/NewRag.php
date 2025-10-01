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
use Sigmie\AI\Role;
use Sigmie\Document\Hit;

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

    protected function preparePrompt(): NewRagPrompt
    {
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

        $groupedHits = $this->searchBuilder->groupedHits();
        $documentHits = $groupedHits['documents'] ?? [];
        $historyHits = $groupedHits['history'] ?? [];

        if ($this->reranker && $this->rerankBuilder) {
            $documentHits = $this->rerankBuilder->rerank($documentHits);
        }

        $messages = array_merge(
            ...array_map(function (Hit $hit) {
                return array_map(
                    fn(array $turn) => [
                        'role'    => Role::from($turn['role']),
                        'content' => $turn['content'],
                    ],
                    $hit->_source['turns']
                );
            }, $historyHits)
        );

        $prompt = new NewRagPrompt($documentHits, $messages);

        if ($this->promptBuilder) {
            ($this->promptBuilder)($prompt);
        }

        return $prompt;
    }

    /**
     * Get JSON structured answer
     */
    public function json(): array
    {
        $prompt = $this->preparePrompt();

        return $this->llm->jsonAnswer($prompt);
    }

    /**
     * Get answer without streaming (returns complete response)
     */
    public function answer()
    {
        $prompt = $this->preparePrompt();

        // Set conversation context
        $conversationId = $this->conversationId ?: prefix_id('conv', 10);

        $answer = $this->llm->answer($prompt);

        $answer->conversation($conversationId);

        $turn = [
            ...array_filter(
                $prompt->messages(),
                fn($message) => $message['role'] === Role::User
            ),
            [
                'role' => Role::Model,
                'content' => $answer->__toString(),
            ]
        ];

        // The answer now contains all the conversation data
        $this->historyIndex?->store(
            $conversationId,
            $turn,
            $answer->model(),
            $answer->timestamp,
            $this->userToken,
        );

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
