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

    protected function executeSearch(): array
    {
        if (!$this->searchBuilder) {
            throw new RuntimeException('Search must be configured before calling answer()');
        }

        if ($this->searchBuilder instanceof NewSearch) {
            $multiSearch = new NewMultiSearch(
                $this->searchBuilder->elasticsearchConnection,
                $this->searchBuilder->embeddingsApi
            );

            $multiSearch->add($this->searchBuilder);

            $this->searchBuilder = $multiSearch;
        }

        $historySearchName = prefix_id('sgm_hist', 5);

        if ($this->historyIndex) {
            $search = $this->historyIndex->search(
                $this->conversationId ?: prefix_id('conv', 10),
                $this->userToken
            );

            $this->searchBuilder->add(
                $search,
                name: $historySearchName
            );
        }

        $groupedHits = $this->searchBuilder->groupedHits();

        // Separate history from document searches
        $historyHits = $groupedHits[$historySearchName] ?? [];
        $documentHits = [];

        foreach ($groupedHits as $key => $hits) {
            if ($key !== $historySearchName ?? null) {
                $documentHits = [...$documentHits, ...$hits];
            }
        }

        return [$documentHits, $historyHits];
    }

    protected function executeRerank(array $documentHits): array
    {
        if (!$this->reranker || !$this->rerankBuilder) {
            return $documentHits;
        }

        return $this->rerankBuilder->rerank($documentHits);
    }

    protected function buildPrompt(array $documentHits, array $historyHits): NewRagPrompt
    {
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

    protected function preparePrompt(): NewRagPrompt
    {
        [$documentHits, $historyHits] = $this->executeSearch();
        $documentHits = $this->executeRerank($documentHits);

        return $this->buildPrompt($documentHits, $historyHits);
    }

    protected function storeConversation(NewRagPrompt $prompt, string $answerContent, string $model, int $timestamp): void
    {
        if (!$this->historyIndex) {
            return;
        }

        $conversationId = $this->conversationId ?: prefix_id('conv', 10);

        $turn = [
            ...array_filter(
                $prompt->messages(),
                fn($message) => $message['role'] === Role::User
            ),
            [
                'role' => Role::Model,
                'content' => $answerContent,
            ]
        ];

        $this->historyIndex->store(
            $conversationId,
            $turn,
            $model,
            (string) $timestamp,
            $this->userToken,
        );
    }

    /**
     * Get JSON structured answer
     */
    public function jsonAnswer()
    {
        $prompt = $this->preparePrompt();

        $conversationId = $this->conversationId ?: prefix_id('conv', 10);

        $answer = $this->llm->jsonAnswer($prompt);

        $answer->conversation($conversationId);

        $this->storeConversation($prompt, $answer->__toString(), $answer->model(), (int) $answer->timestamp);

        return $answer;
    }

    /**
     * Get answer without streaming (returns complete response)
     */
    public function answer()
    {
        $prompt = $this->preparePrompt();

        $conversationId = $this->conversationId ?: prefix_id('conv', 10);

        $answer = $this->llm->answer($prompt);

        $answer->conversation($conversationId);

        $this->storeConversation($prompt, $answer->__toString(), $answer->model(), (int) $answer->timestamp);

        return $answer;
    }

    /**
     * Stream answer with real-time events and chunks
     */
    public function streamAnswer(): iterable
    {
        yield ['type' => 'search_start', 'timestamp' => microtime(true)];

        [$documentHits, $historyHits] = $this->executeSearch();

        yield ['type' => 'search_complete', 'hits' => count($documentHits), 'timestamp' => microtime(true)];

        if ($this->reranker && $this->rerankBuilder) {
            yield ['type' => 'rerank_start', 'timestamp' => microtime(true)];

            $documentHits = $this->executeRerank($documentHits);

            yield ['type' => 'rerank_complete', 'hits' => count($documentHits), 'timestamp' => microtime(true)];
        } else {
            $documentHits = $documentHits;
        }

        yield ['type' => 'prompt_start', 'timestamp' => microtime(true)];

        $prompt = $this->buildPrompt($documentHits, $historyHits);

        yield ['type' => 'prompt_complete', 'timestamp' => microtime(true)];

        yield ['type' => 'llm_start', 'timestamp' => microtime(true)];

        $fullAnswer = '';

        foreach ($this->llm->streamAnswer($prompt) as $chunk) {
            $fullAnswer .= $chunk;
            yield ['type' => 'llm_chunk', 'content' => $chunk, 'timestamp' => microtime(true)];
        }

        yield ['type' => 'llm_complete', 'timestamp' => microtime(true)];

        yield ['type' => 'turn_store_start', 'timestamp' => microtime(true)];

        $this->storeConversation($prompt, $fullAnswer, $this->llm->model(), time());

        yield ['type' => 'turn_store_complete', 'timestamp' => microtime(true)];
    }
}
