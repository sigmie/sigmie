# RAG (Retrieval-Augmented Generation)

## What is RAG?

RAG (Retrieval-Augmented Generation) combines the power of Elasticsearch search with Large Language Models (LLMs) to provide contextually accurate and grounded answers to user questions. Instead of relying solely on the LLM's training data, RAG retrieves relevant documents from your Elasticsearch indices and uses them as context to generate more accurate, fact-based responses.

The RAG process in Sigmie works in three key steps:
1. **Search**: Query your Elasticsearch indices to find relevant documents
2. **Rerank** (optional): Improve result relevance using advanced reranking algorithms
3. **Generate**: Use the retrieved context with an LLM to generate comprehensive answers

## Basic Usage

Here's a simple example of using RAG in Sigmie:

```php
use Sigmie\AI\LLMs\OpenAILLM;
use Sigmie\Search\NewRagPrompt;

// Initialize your LLM
$llm = new OpenAILLM('your-openai-api-key');

// Create a basic RAG query
$answer = $sigmie
    ->newRag($llm)
    ->search(
        $sigmie->newSearch('my-index')
            ->queryString('What is machine learning?')
            ->size(5)
    )
    ->prompt(function (NewRagPrompt $prompt) {
        $prompt->question('What is machine learning?');
        $prompt->contextFields(['title', 'content']);
        $prompt->guardrails([
            'Answer only from provided context',
            'Be concise and factual'
        ]);
    })
    ->instructions('You are a helpful technical assistant.')
    ->limits(maxTokens: 500, temperature: 0.1)
    ->answer();

echo $answer['answer'];
```

## Configuration Options

### search()

Configure the Elasticsearch search that will retrieve relevant documents:

```php
$rag->search(
    $sigmie->newSearch('documents')
        ->queryString('artificial intelligence')
        ->retrieve(['title', 'content', 'author'])
        ->size(10)
        ->filters('category:technology')
);
```

The search can be any `NewSearch` object with all standard Sigmie search capabilities.

### multiSearch()

Use multiple searches to gather context from different indices or with different queries:

```php
$rag->multiSearch(function ($multiSearch) {
    $multiSearch
        ->newSearch('articles')
        ->queryString('machine learning basics')
        ->size(3);
        
    $multiSearch
        ->newSearch('tutorials') 
        ->queryString('ML getting started')
        ->size(2);
});
```

### reranker() and rerank()

Improve search result relevance using advanced reranking:

```php
use Sigmie\AI\Rerankers\VoyageReranker;
use Sigmie\Rag\NewRerank;

$voyageReranker = new VoyageReranker('your-voyage-api-key');

$rag->reranker($voyageReranker)
    ->rerank(function (NewRerank $rerank) {
        $rerank->fields(['title', 'content']);
        $rerank->topK(5);  // Keep top 5 results after reranking
        $rerank->query('What is machine learning?');
    });
```

**Available Rerankers:**
- `VoyageReranker` - High-quality semantic reranking
- Custom rerankers implementing `Sigmie\AI\Contracts\Reranker`

### prompt()

Customize how the retrieved context is formatted into the LLM prompt:

```php
$rag->prompt(function (NewRagPrompt $prompt) {
    $prompt->question('What are the benefits of renewable energy?');
    
    // Specify which fields from search results to include
    $prompt->contextFields(['title', 'summary', 'key_points']);
    
    // Add guardrails to guide the LLM's behavior
    $prompt->guardrails([
        'Answer only from provided context',
        'Do not fabricate facts', 
        'Be concise and use bullet points when possible',
        'Cite sources as [^id]'
    ]);
    
    // Use a custom prompt template
    $prompt->template('
        Question: {{question}}
        
        Guidelines: {{guardrails}}
        
        Relevant Information:
        {{context}}
        
        Please provide a comprehensive answer:
    ');
});
```

**Template Variables:**
- `{{question}}` - The user's question
- `{{context}}` - JSON-formatted search results
- `{{guardrails}}` - List of behavioral guidelines
- `{{hits}}` - Alias for `{{context}}`

### instructions()

Set system-level instructions for the LLM:

```php
$rag->instructions(
    "You are a precise, no-fluff technical assistant. " .
    "Answer in English. Cite sources as [^id]. " .
    "If information is not in the context, say 'Unknown.'"
);
```

### limits()

Configure LLM generation parameters:

```php
$rag->limits(
    maxTokens: 800,    // Maximum response length
    temperature: 0.1   // Creativity level (0.0 = deterministic, 1.0 = creative)
);
```

## Methods

### answer()

Get a complete answer synchronously:

```php
$response = $rag->answer();

// Response structure:
[
    'answer' => 'The generated response text...',
    'usage' => [
        'prompt_tokens' => 150,
        'completion_tokens' => 200,
        'total_tokens' => 350
    ],
    'model' => 'gpt-4'
]
```

### streamAnswer()

Stream the answer in real-time for better user experience:

```php
foreach ($rag->streamAnswer() as $chunk) {
    echo $chunk['content'];
    
    if ($chunk['finish_reason'] === 'stop') {
        break;
    }
}
```

## Advanced Examples

### Semantic Search with Reranking

```php
use Sigmie\AI\LLMs\OpenAILLM;
use Sigmie\AI\Rerankers\VoyageReranker;
use Sigmie\Mappings\NewProperties;

$openai = new OpenAILLM('your-openai-api-key');
$voyageReranker = new VoyageReranker('your-voyage-api-key');

// Set up semantic search properties
$props = new NewProperties;
$props->text('title')->semantic(accuracy: 1, dimensions: 256);
$props->text('content')->semantic(accuracy: 1, dimensions: 256);

$answer = $sigmie
    ->newRag($openai)
    ->reranker($voyageReranker)
    ->search(
        $sigmie->newSearch('knowledge-base')
            ->properties($props)
            ->semantic('How does photosynthesis work?', ['title', 'content'])
            ->retrieve(['title', 'content', 'author'])
            ->size(10)
    )
    ->rerank(function ($rerank) {
        $rerank->fields(['title', 'content']);
        $rerank->topK(3);
        $rerank->query('How does photosynthesis work?');
    })
    ->prompt(function ($prompt) {
        $prompt->question('How does photosynthesis work?');
        $prompt->contextFields(['title', 'content']);
        $prompt->guardrails([
            'Explain in simple terms',
            'Use scientific accuracy',
            'Include key steps of the process'
        ]);
    })
    ->instructions('You are a biology teacher explaining complex topics clearly.')
    ->limits(maxTokens: 600, temperature: 0.2)
    ->answer();
```

### Custom Prompt Template

```php
$rag->prompt(function (NewRagPrompt $prompt) {
    $prompt->question('What are the latest trends in AI?');
    $prompt->contextFields(['headline', 'summary', 'published_date']);
    
    $prompt->template('
        QUESTION: {{question}}
        
        INSTRUCTIONS:
        {{guardrails}}
        
        RECENT ARTICLES:
        {{context}}
        
        ANALYSIS:
        Based on the articles above, provide a comprehensive trend analysis.
    ');
    
    $prompt->guardrails([
        'Focus on recent developments (last 12 months)',
        'Organize by trend categories',
        'Include timeline information when available'
    ]);
});
```

### Streaming with Progress Updates

```php
echo "Searching for relevant documents...\n";

$stream = $rag->streamAnswer();

echo "Generating response:\n";
foreach ($stream as $chunk) {
    echo $chunk['content'];
    flush();
    
    if ($chunk['finish_reason']) {
        echo "\n\nGeneration complete: " . $chunk['finish_reason'] . "\n";
        break;
    }
}
```

### Multi-Index Research

```php
$rag->multiSearch(function ($multiSearch) {
    // Search academic papers
    $multiSearch
        ->newSearch('papers')
        ->queryString('climate change impacts')
        ->filters('peer_reviewed:true')
        ->size(3);
        
    // Search news articles  
    $multiSearch
        ->newSearch('news')
        ->queryString('climate change 2024')
        ->filters('published_date:[2024-01-01 TO *]')
        ->size(2);
        
    // Search government reports
    $multiSearch
        ->newSearch('reports')
        ->queryString('climate policy')
        ->filters('source:government')
        ->size(2);
})
->prompt(function ($prompt) {
    $prompt->question('What are the current climate change impacts and policy responses?');
    $prompt->contextFields(['title', 'abstract', 'key_findings']);
    $prompt->guardrails([
        'Distinguish between academic research, news reports, and policy documents',
        'Note publication dates and sources',
        'Highlight consensus vs. conflicting information'
    ]);
});
```

## Best Practices

### Search Optimization
- **Use semantic search** for conceptual queries when you have embedding-enabled fields
- **Limit result size** to 5-10 documents to avoid overwhelming the LLM context window
- **Filter strategically** to ensure retrieved documents are relevant and recent
- **Include diverse fields** in retrieval to provide comprehensive context

### Reranking Strategy
- **Apply reranking** when you have more than 5 initial results to improve precision
- **Use reranking for cross-lingual** queries where semantic similarity is crucial
- **Configure topK** to balance context richness with focus (typically 3-5 documents)

### Prompt Engineering
- **Be specific** in your question formulation
- **Use guardrails** to prevent hallucination and ensure factual accuracy
- **Customize context fields** to include only relevant information
- **Test different templates** for your specific use case

### LLM Configuration
- **Lower temperature** (0.0-0.3) for factual, deterministic responses
- **Higher temperature** (0.5-0.8) for creative or brainstorming tasks
- **Set appropriate token limits** based on your use case (300-800 for summaries, 1000+ for detailed analysis)

### Performance Tips
- **Use streaming** for long responses to improve perceived performance
- **Cache frequent queries** at the application level
- **Monitor token usage** to optimize costs
- **Index optimization** - ensure your search indices are properly configured for your RAG queries

### Error Handling
```php
try {
    $answer = $rag->answer();
} catch (\RuntimeException $e) {
    if (str_contains($e->getMessage(), 'Search must be configured')) {
        // Handle missing search configuration
        echo "Please configure a search query first";
    }
} catch (\Exception $e) {
    // Handle API errors, network issues, etc.
    echo "RAG query failed: " . $e->getMessage();
}
```

### Security Considerations
- **Validate user input** before using in search queries
- **Implement rate limiting** for expensive RAG operations
- **Filter sensitive information** from context fields
- **Use appropriate API key permissions** for your LLM and reranking services