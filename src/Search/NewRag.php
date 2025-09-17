<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Closure;
use Sigmie\AI\Contracts\Embedder;
use Sigmie\AI\Contracts\LLM;
use Sigmie\AI\Contracts\Reranker;
use Sigmie\AI\ProviderFactory;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Document\Hit;
use Sigmie\Document\RerankedHit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Rag\NewRerank;
use Sigmie\Rag\RagResponse;
use Sigmie\Search\Formatters\SigmieSearchResponse;

class NewRag
{
    protected null|NewSearch|NewMultiSearch $searchBuilder = null;

    protected ?Reranker $reranker = null;

    protected ?Closure $promptBuilder = null;

    protected ?NewRerank $rerankBuilder = null;

    protected string $instructions = '';

    protected array $llmOptions = [];

    public function __construct(
        protected ElasticsearchConnection $connection,
        protected ?LLM $llm = null,
        protected ?Embedder $embedder = null
    ) {}

    public function search(NewSearch|NewMultiSearch $search): self
    {
        $this->searchBuilder = $search;

        return $this;
    }

    public function multiSearch(Closure $callback): self
    {
        $this->searchBuilder = new NewMultiSearch($this->connection);

        $callback($this->searchBuilder);

        return $this;
    }

    public function reranker(Reranker $reranker): self
    {
        $this->reranker = $reranker;

        return $this;
    }

    public function rerank(Closure $callback): self
    {
        $this->rerankBuilder = new NewRerank($this->reranker);

        $callback($this->rerankBuilder);

        return $this;
    }

    public function prompt(Closure $callback): self
    {
        $this->promptBuilder = $callback;

        return $this;
    }

    public function answer(bool $stream = false): iterable
    {
        // Execute search
        if (!$this->searchBuilder) {
            throw new \RuntimeException('Search must be configured before calling answer()');
        }

        // Create temporary RagResponse for events
        $ragResponse = new RagResponse([], null, null);

        if ($stream) {
            // Emit search started event
            yield $ragResponse->searchingEvent();
        }

        $searchResponse = $this->searchBuilder->get();
        $retrievedHits = $searchResponse->hits();
        
        if ($stream) {
            // Emit search completed event
            yield $ragResponse->searchCompleteEvent(count($retrievedHits));
        }

        $rerankedHits = null;

        // Apply reranking if configured
        if ($this->reranker && $this->rerankBuilder) {
            if ($stream) {
                // Emit reranking started event
                yield $ragResponse->rerankingEvent();
            }
            
            $rerankedHits = $this->rerankBuilder->rerank($retrievedHits)->hits();
            $hits = $rerankedHits;
            
            if ($stream) {
                // Emit reranking completed event
                yield $ragResponse->rerankCompleteEvent(count($retrievedHits), count($rerankedHits));
            }
        } else {
            $hits = $retrievedHits;
        }

        $prompt = new NewRagPrompt($hits);
        ($this->promptBuilder)($prompt);

        // Build and execute prompt
        $finalPrompt = $prompt->create();

        if ($stream) {
            // Emit prompt generation event
            yield $ragResponse->promptGenerationEvent();
        }

        // Create final RagResponse with all data
        $ragResponse = new RagResponse(
            hits: $retrievedHits,
            rerankedHits: $rerankedHits,
            ragPrompt: $finalPrompt
        );

        if ($stream) {
            // Yield the stream start with context
            yield $ragResponse->startStreamingChunk();
            
            // Emit LLM request started event
            yield $ragResponse->llmRequestEvent();

            // Track if this is the first token
            $firstToken = true;

            // Stream the answer directly from LLM without buffering
            foreach ($this->llm->answer($finalPrompt, $this->instructions, true) as $chunk) {
                if ($firstToken && !empty($chunk)) {
                    // Emit first token event
                    yield $ragResponse->llmFirstTokenEvent();
                    $firstToken = false;
                }
                // Pass through content chunks immediately as they arrive
                yield $ragResponse->streamingChunk($chunk);
            }

            // Yield completion signal
            yield $ragResponse->streamingChunk('', true);
            return;
        }

        // Get complete answer
        $answer = '';

        foreach ($this->llm->answer($finalPrompt, $this->instructions, false) as $chunk) {
            $answer .= $chunk;
        }
        $ragResponse->setFinalAnswer($answer);

        // Return complete response
        yield $ragResponse;
    }

    public function instructions(string $instructions): self
    {
        $this->instructions = $instructions;

        return $this;
    }
}
