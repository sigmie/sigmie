<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Closure;
use Sigmie\AI\Contracts\EmbeddingProvider;
use Sigmie\AI\Contracts\LLM;
use Sigmie\AI\Contracts\Reranker;
use Sigmie\AI\ProviderFactory;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Document\Hit;
use Sigmie\Document\RerankedHit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Semantic\Providers\SigmieAI;

class NewRag
{
    protected ElasticsearchConnection $connection;
    protected string $index;
    protected NewProperties|Properties $properties;

    protected ?EmbeddingProvider $embeddingProvider = null;
    protected ?Reranker $reranker = null;
    protected ?LLM $llm = null;
    protected ?int $rerankTopK = null;

    protected ?array $retrieve = null;
    protected string $question = '';
    protected string $filters = '';
    protected int $size = 10;

    protected ?Closure $contextComposer = null;
    protected NewPrompt $promptBuilder;

    protected array $llmOptions = [];

    // Legacy support
    protected ?SigmieAI $aiProvider = null;
    protected ?string $prompt = null;
    protected bool $rerank = false;

    public function __construct(ElasticsearchConnection $connection)
    {
        $this->connection = $connection;
        $this->contextComposer = fn(Hit $hit) => $hit;
        $this->promptBuilder = new NewPrompt();

        // Default to SigmieAI provider
        $sigmieAI = new SigmieAI();
        $this->embeddingProvider = $sigmieAI;
        $this->reranker = $sigmieAI;
        $this->llm = $sigmieAI;
        $this->aiProvider = $sigmieAI;
    }

    public function index(string $index): self
    {
        $this->index = $index;
        return $this;
    }

    public function aiProvider(SigmieAI $aiProvider): self
    {
        $this->aiProvider = $aiProvider;
        $this->embeddingProvider = $aiProvider;
        $this->reranker = $aiProvider;
        $this->llm = $aiProvider;
        return $this;
    }

    public function properties(Properties|NewProperties $props): self
    {
        $this->properties = $props;
        return $this;
    }

    public function embedWith(?string $provider = null, ?string $model = null): self
    {
        if ($provider === null) {
            // Keep default SigmieAI
            return $this;
        }
        $this->embeddingProvider = ProviderFactory::createEmbeddingProvider($provider, $model);
        return $this;
    }

    public function rerankWith(string $provider, ?string $model = null, ?int $topK = null): self
    {
        $this->reranker = ProviderFactory::createReranker($provider, $model);
        $this->rerankTopK = $topK;
        $this->rerank = true;
        return $this;
    }

    public function llm(string $provider, ?string $model = null, ?int $maxTokens = null, ?float $temperature = null): self
    {
        $this->llm = ProviderFactory::createLLM($provider, $model);

        if ($maxTokens !== null) {
            $this->llmOptions['max_tokens'] = $maxTokens;
        }
        if ($temperature !== null) {
            $this->llmOptions['temperature'] = $temperature;
        }

        return $this;
    }

    public function retrieve(array $retrieve): self
    {
        $this->retrieve = $retrieve;
        return $this;
    }

    public function question(string $question): self
    {
        $this->question = $question;
        return $this;
    }

    public function filter(string $filter): self
    {
        $this->filters = $filter;
        return $this;
    }

    public function size(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function compose(callable $callback): self
    {
        $this->contextComposer = $callback;

        return $this;
    }

    public function prompt($callbackOrString): self
    {
        if (is_callable($callbackOrString)) {
            $callbackOrString($this->promptBuilder);
        } else {
            // Legacy string prompt support
            $this->prompt = $callbackOrString;
        }
        return $this;
    }

    public function rerank(): self
    {
        $this->rerank = true;
        return $this;
    }

    public function answer(): array
    {
        // Create a new search with the configured parameters
        $search = new NewSearch($this->connection);

        $searchQuery = $search->properties($this->properties)
            ->index($this->index)
            ->queryString($this->question)
            ->filters($this->filters)
            ->size($this->size);

        if ($this->retrieve) {
            $searchQuery = $searchQuery->retrieve($this->retrieve);
        }

        // Set AI provider if it's a SigmieAI instance
        if ($this->aiProvider instanceof SigmieAI) {
            $searchQuery->aiProvider($this->aiProvider);
        }

        $response = $searchQuery->get();
        $hits = $response->hits();

        // Rerank if configured
        if ($this->rerank && $this->reranker) {

            $documents = array_map(fn(Hit $hit) => $this->reranker->formatHit($hit), $hits);

            $rerankedScores = $this->reranker->rerank($documents, $this->question);

            $rerankedHits = array_map(
                fn($rerankedScore, $index) => new RerankedHit($hits[$index], $rerankedScore),
                $rerankedScores,
                array_keys($rerankedScores)
            );

            $hits = $rerankedHits;
        }

        // Build context from reranked documents
        $context = $this->buildContext($hits);

        // Build and execute prompt
        $finalPrompt = $this->buildPrompt($context);

        // Get answer from LLM
        $llmResponse = $this->llm->answer(
            $finalPrompt,
            $this->promptBuilder->getSystemPrompt() ?: '',
            $this->llmOptions
        );

        return $llmResponse;
    }

    protected function buildContext(array $hits): string
    {
        $res = [];

        /** @var Hit|RerankedHit $hit */
        foreach ($hits as $index => $hit) {
            $res[] = json_encode($hit->_source);
        }

        return implode("\n\n", $res);
    }

    protected function buildPrompt(string $context): string
    {
        // Use new prompt builder if configured, otherwise fall back to legacy
        if ($this->promptBuilder->getTemplate()) {
            $template = $this->promptBuilder->getTemplate();
            $prompt = str_replace('{{context}}', $context, $template);
            $prompt = str_replace('{{question}}', $this->question, $prompt);
            return $prompt;
        } elseif ($this->prompt) {
            // Legacy prompt support
            $prompt = str_replace('{{context}}', $context, $this->prompt);
            $prompt = str_replace('{{question}}', $this->question, $prompt);
            return $prompt;
        } else {
            // Default prompt
            return "Question: {$this->question}\n\nContext:\n{$context}";
        }
    }
}
