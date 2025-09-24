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

class NewRag
{
    protected ?NewMultiSearch $searchBuilder = null;
    protected ?NewRerank $rerankBuilder = null;
    protected ?\Closure $promptBuilder = null;
    protected string $instructions = '';

    public function __construct(
        protected LLMApi $llm,
        protected ?RerankApi $reranker = null
    ) {}

    public function search(NewMultiSearch $builder): self
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

    /**
     * Get answer without streaming (returns complete response)
     */
    public function answer(): LLMAnswer
    {
        // Execute search
        if (!$this->searchBuilder) {
            throw new RuntimeException('Search must be configured before calling answer()');
        }

        $hits = $this->searchBuilder->hits();

        // Apply reranking if configured
        if ($this->reranker && $this->rerankBuilder) {
            $hits = $this->rerankBuilder->rerank($hits);
        }

        $prompt = new NewRagPrompt($hits);

        if ($this->promptBuilder) {
            ($this->promptBuilder)($prompt);
        }

        return $this->llm->answer($prompt);
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

        $hits = $this->searchBuilder->hits();
        
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
