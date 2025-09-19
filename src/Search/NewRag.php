<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\AI\Contracts\LLMApi;
use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Rag\NewRerank;
use Sigmie\Rag\RagAnswer;
use Sigmie\Rag\RagResponse;
use Sigmie\Search\Contracts\MultiSearchResponse;
use Sigmie\Search\Formatters\SigmieSearchResponse;

class NewRag
{
    protected ?NewMultiSearch $searchBuilder = null;
    protected ?NewRerank $rerankBuilder = null;
    protected ?RerankApi $reranker = null;
    protected ?\Closure $promptBuilder = null;
    protected string $instructions = '';

    public function __construct(protected LLMApi $llm) {}

    public function search(NewMultiSearch $builder): self
    {
        $this->searchBuilder = $builder;

        return $this;
    }

    public function reranker(RerankApi $reranker): self
    {
        $this->reranker = $reranker;

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

    /**
     * Get answer without streaming (returns complete response)
     */
    public function answer(): RagAnswer
    {
        // Execute search
        if (!$this->searchBuilder) {
            throw new \RuntimeException('Search must be configured before calling answer()');
        }

        $hits = $this->searchBuilder->hits();

        // Apply reranking if configured
        if ($this->reranker && $this->rerankBuilder) {
            $rerankedHits = $this->rerankBuilder->rerank($hits)->hits();

            $hits = $rerankedHits;
        }

        // Build prompt
        $prompt = $this->buildPrompt($hits);

        $data = $this->llm->answer($prompt, $this->instructions);

        return new RagAnswer($hits, $prompt, $data);
    }

    /**
     * Stream answer with real-time events and chunks
     */
    public function streamAnswer(): iterable
    {
        // Execute search
        if (!$this->searchBuilder) {
            throw new \RuntimeException('Search must be configured before calling streamAnswer()');
        }

        // Create temporary RagAnswer for events
        $ragAnswer = new RagAnswer([], null, null);

        // Emit search started event
        yield $ragAnswer->searchingEvent();

        $searchResults = $this->searchBuilder->get();
        // Get the first search response (we only have one search in the multi-search)
        $searchResponse = $searchResults[0] ?? null;
        if (!$searchResponse) {
            throw new \RuntimeException('No search results returned');
        }
        $retrievedHits = $searchResponse->hits();

        // Emit search completed event
        yield $ragAnswer->searchCompleteEvent(count($retrievedHits));

        $rerankedHits = null;

        // Apply reranking if configured
        if ($this->reranker && $this->rerankBuilder) {
            // Emit reranking started event
            yield $ragAnswer->rerankingEvent();

            $rerankedHits = $this->rerankBuilder->rerank($retrievedHits)->hits();
            $hits = $rerankedHits;

            // Emit reranking completed event
            yield $ragAnswer->rerankCompleteEvent(count($retrievedHits), count($rerankedHits));
        } else {
            $hits = $retrievedHits;
        }

        // Build prompt
        $finalPrompt = $this->buildPrompt($hits);

        // Emit prompt generation event
        yield $ragAnswer->promptGenerationEvent();

        // Create final RagAnswer with all data
        $ragAnswer = new RagAnswer(
            hits: $retrievedHits,
            rerankedHits: $rerankedHits,
            ragPrompt: $finalPrompt,
            metadata: []
        );

        // Yield the stream start with context
        yield $ragAnswer->startStreamingChunk();

        // Emit LLM request started event
        yield $ragAnswer->llmRequestEvent();

        // Track if this is the first token
        $firstToken = true;
        $fullAnswer = '';
        $conversationId = null;

        // Stream the answer
        foreach ($this->llm->streamAnswer($finalPrompt, $this->instructions) as $chunk) {
            // Handle conversation events
            if (is_array($chunk)) {
                // Yield conversation events directly
                yield $chunk;

                // Extract conversation ID if present
                if (isset($chunk['type'])) {
                    if ($chunk['type'] === 'conversation.created' || $chunk['type'] === 'conversation.reused') {
                        $conversationId = $chunk['conversation_id'] ?? null;
                        if ($conversationId) {
                            $ragAnswer->setConversationId($conversationId);
                        }
                    }
                }
            } elseif (is_string($chunk)) {
                // Only process string chunks as answer text
                if ($firstToken) {
                    // Emit first token event
                    yield $ragAnswer->llmFirstTokenEvent();
                    $firstToken = false;
                }

                // Yield the text chunk
                yield $ragAnswer->streamingChunk($chunk);
                $fullAnswer .= $chunk;
            }
        }

        $ragAnswer->setFinalAnswer($fullAnswer);

        // Yield completion signal
        yield $ragAnswer->streamingChunk('', true);
    }

    public function instructions(string $instructions): self
    {
        $this->instructions = $instructions;

        return $this;
    }

    /**
     * Build the final prompt from hits
     */
    protected function buildPrompt(array $hits): string
    {
        $prompt = new NewRagPrompt($hits);

        if ($this->promptBuilder) {
            ($this->promptBuilder)($prompt);
        }

        return $prompt->create();
    }
}
