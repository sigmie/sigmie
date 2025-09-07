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
use Sigmie\Semantic\Providers\SigmieAI;
use Sigmie\Search\Formatters\SigmieSearchResponse;

class NewRag
{
    protected null|NewSearch|NewMultiSearch $searchBuilder = null;

    protected ?NewRerank $rerankBuilder = null;

    protected ?NewPrompt $promptBuilder = null;

    public function __construct(
        protected ElasticsearchConnection $connection,
        protected ?LLM $llm = null,
        protected ?Reranker $reranker = null,
    ) {
        $this->promptBuilder = new NewPrompt();
    }

    public function search(Closure $callback): self
    {
        $this->searchBuilder = new NewSearch($this->connection);

        $callback($this->searchBuilder);

        return $this;
    }

    public function multiSearch(Closure $callback): self
    {
        $this->searchBuilder = new NewMultiSearch($this->connection);

        $callback($this->searchBuilder);

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
        $this->promptBuilder = new NewPrompt();
        $callback($this->promptBuilder);
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

        dd($hits);

        // Apply reranking if configured
        if ($this->rerankBuilder) {
            $rerankedResponse = $this->rerankBuilder->rerank($searchResponse);
            $hits = $rerankedResponse->hits();
        }

        dump($hits);

        // Build context from hits
        $context = $this->buildContext($hits);

        // Build and execute prompt
        $finalPrompt = $this->buildPrompt($context);

        dump($context, $finalPrompt);

        // Get answer from LLM
        $llmResponse = $this->llm->answer(
            $finalPrompt,
            $this->promptBuilder->getInstructions() ?: '',
            $this->llmOptions
        );

        dump($llmResponse);

        return $llmResponse;
    }

    protected function buildContext(array $hits): string
    {
        // If prompt builder has a context composer, use it
        if ($this->promptBuilder && $this->promptBuilder->getContextComposer()) {
            return $this->promptBuilder->getContextComposer()->compose($hits);
        }

        // Default context building
        $res = [];
        /** @var Hit|RerankedHit $hit */
        foreach ($hits as $hit) {
            $res[] = json_encode($hit->_source);
        }

        return implode("\n\n", $res);
    }

    protected function buildPrompt(string $context): string
    {
        if ($this->promptBuilder && $this->promptBuilder->getTemplate()) {
            $template = $this->promptBuilder->getTemplate();
            $prompt = str_replace('{{context}}', $context, $template);
            $prompt = str_replace('{{question}}', $this->promptBuilder->getQuestion(), $prompt);
            return $prompt;
        }

        // Default prompt
        $question = $this->promptBuilder ? $this->promptBuilder->getQuestion() : '';
        return "Question: {$question}\n\nContext:\n{$context}";
    }
}
