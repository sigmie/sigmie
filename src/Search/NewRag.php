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

    public function answer(): array
    {
        // Execute search
        if (!$this->searchBuilder) {
            throw new \RuntimeException('Search must be configured before calling answer()');
        }

        $searchResponse = $this->searchBuilder->get();

        $hits = $searchResponse->hits();

        // Apply reranking if configured
        if ($this->reranker && $this->rerankBuilder) {
            $hits = $this->rerankBuilder->rerank($hits)->hits();
        }

        $prompt = new NewRagPrompt($hits);

        ($this->promptBuilder)($prompt);

        // Build and execute prompt
        $finalPrompt = $prompt->create();

        // Get answer from LLM
        $llmResponse = $this->llm->answer(
            $finalPrompt,
            $this->instructions,
            $this->llmOptions['max_tokens'],
            $this->llmOptions['temperature']
        );

        dump($llmResponse);

        return $llmResponse;
    }

    public function instructions(string $instructions): self
    {
        $this->instructions = $instructions;

        return $this;
    }

    public function limits(int $maxTokens, float $temperature): self
    {
        $this->llmOptions = [
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ];

        return $this;
    }
}
