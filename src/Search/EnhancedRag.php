<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\AI\Contracts\EmbeddingProvider;
use Sigmie\AI\Contracts\LLM;
use Sigmie\AI\Contracts\Reranker;
use Sigmie\AI\ProviderFactory;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Semantic\Providers\SigmieAI;

class EnhancedRag
{
    protected ElasticsearchConnection $connection;
    protected string $index;
    protected NewProperties|Properties $properties;
    
    protected ?EmbeddingProvider $embeddingProvider = null;
    protected ?Reranker $reranker = null;
    protected ?LLM $llm = null;
    protected ?int $rerankTopK = null;
    
    protected array $fields = [];
    protected string $question = '';
    protected string $filter = '';
    protected int $size = 10;
    
    protected NewContextComposer $contextComposer;
    protected NewPrompt $promptBuilder;
    
    protected array $llmOptions = [];

    public function __construct(ElasticsearchConnection $connection, string $index)
    {
        $this->connection = $connection;
        $this->index = $index;
        $this->contextComposer = new NewContextComposer();
        $this->promptBuilder = new NewPrompt();
        
        // Default to SigmieAI provider
        $sigmieAI = new SigmieAI();
        $this->embeddingProvider = $sigmieAI;
        $this->reranker = $sigmieAI;
        $this->llm = $sigmieAI;
    }

    public function properties(Properties|NewProperties $props): self
    {
        $this->properties = $props;
        return $this;
    }

    public function embedWith(string $provider, ?string $model = null): self
    {
        $this->embeddingProvider = ProviderFactory::createEmbeddingProvider($provider, $model);
        return $this;
    }

    public function rerankWith(string $provider, ?string $model = null, ?int $topK = null): self
    {
        $this->reranker = ProviderFactory::createReranker($provider, $model);
        $this->rerankTopK = $topK;
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

    public function fields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    public function question(string $question): self
    {
        $this->question = $question;
        return $this;
    }

    public function filter(string $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    public function size(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function compose(callable $callback): self
    {
        $callback($this->contextComposer);
        return $this;
    }

    public function prompt(callable $callback): self
    {
        $callback($this->promptBuilder);
        return $this;
    }

    public function answer(): array
    {
        // Create a new search with the configured parameters
        $search = new NewSearch($this->connection);
        
        $searchQuery = $search->properties($this->properties)
            ->index($this->index)
            ->queryString($this->question)
            ->filters($this->filter)
            ->size($this->size);
        
        // // Set AI provider if it's a SigmieAI instance
        // if ($this->embeddingProvider instanceof SigmieAI) {
        //     $searchQuery->aiProvider($this->embeddingProvider);
        // }
        
        $response = $searchQuery->get();
        $hits = $response->json('hits');

        
        // Prepare documents for reranking
        $documents = [];
        foreach ($hits as $hit) {
            $doc = [];
            foreach ($this->fields as $field) {
                if (isset($hit['_source'][$field])) {
                    $doc[$field] = $hit['_source'][$field];
                }
            }
            $documents[] = json_encode($doc);
        }
        
        // Rerank if configured
        if ($this->reranker) {
            $rerankedScores = $this->reranker->rerank($documents, $this->question, $this->rerankTopK);
            
            // Reorder hits based on reranked scores
            // Check if the response is in the format with 'index' keys (Voyage) or just indices (Sigmie)
            $reorderedHits = [];
            foreach ($rerankedScores as $item) {
                if (is_array($item) && isset($item['index'])) {
                    // Voyage format: [{index: 0, score: 0.9}, ...]
                    $reorderedHits[] = $hits[$item['index']];
                } else {
                    // Sigmie format: [2, 0, 1] (indices in order of relevance)
                    $reorderedHits[] = $hits[$item];
                }
            }
            if (!empty($reorderedHits)) {
                $hits = $reorderedHits;
            }
        }
        
        // Build context from reranked documents
        $context = $this->buildContext($hits);
        
        // Build and execute prompt
        $finalPrompt = $this->buildPrompt($context);
        
        // Get answer from LLM
        $llmResponse = $this->llm->answer(
            $finalPrompt,
            $this->promptBuilder->getSystemPrompt(),
            $this->llmOptions
        );
        
        return $llmResponse;
    }

    protected function buildContext(array $hits): string
    {
        $contextParts = [];
        $citationStyle = $this->contextComposer->getCitationStyle();
        
        foreach ($hits as $index => $hit) {
            $docParts = [];
            
            // Add metadata if configured
            foreach ($this->contextComposer->getIncludeMetadata() as $metaField) {
                if (isset($hit['_source'][$metaField])) {
                    $docParts[$metaField] = $hit['_source'][$metaField];
                }
            }
            
            // Add field content
            foreach ($this->fields as $field) {
                if (isset($hit['_source'][$field])) {
                    $docParts[$field] = $hit['_source'][$field];
                }
            }
            
            if ($citationStyle === 'inline') {
                $contextParts[] = '[' . ($index + 1) . '] ' . json_encode($docParts);
            } else {
                $contextParts[] = json_encode($docParts);
            }
        }
        
        return implode("\n\n", $contextParts);
    }

    protected function buildPrompt(string $context): string
    {
        $template = $this->promptBuilder->getTemplate();
        $prompt = str_replace('{{context}}', $context, $template);
        $prompt = str_replace('{{question}}', $this->question, $prompt);
        
        return $prompt;
    }
}
