---
title: RAG
short_description: Combine search with LLMs for retrieval-augmented generation
keywords: [rag, retrieval augmented generation, llm, ai, openai, chatgpt]
category: Features
order: 5
related_pages: [semantic-search, recommendations, search]
---

# RAG (Retrieval-Augmented Generation)

## What is RAG?

RAG (Retrieval-Augmented Generation) combines the power of Elasticsearch search with Large Language Models (LLMs) to provide contextually accurate and grounded answers to user questions. Instead of relying solely on the LLM's training data, RAG retrieves relevant documents from your Elasticsearch indices and uses them as context to generate more accurate, fact-based responses.

The RAG process in Sigmie works in three key steps:
1. **Search**: Query your Elasticsearch indices to find relevant documents
2. **Rerank** (optional): Improve result relevance using advanced reranking algorithms
3. **Generate**: Use the retrieved context with an LLM to generate comprehensive answers

## New Modular API Architecture

Sigmie v2 introduces a modular API architecture that separates concerns and provides more flexibility for different use cases. The system now uses dedicated API classes instead of a single unified LLM class.

### Core Interfaces

- **`EmbeddingsApi`** - Interface for embedding operations
- **`LLMApi`** - Interface for language model operations
- **`RerankApi`** - Interface for reranking operations

### OpenAI API Classes

- **`AbstractOpenAIApi`** - Base class for all OpenAI APIs to avoid code duplication
- **`OpenAIEmbeddingsApi`** - Handles embeddings using text-embedding-3-small model
- **`OpenAIResponseApi`** - Uses the /v1/responses endpoint for simple completions
- **`OpenAIConversationsApi`** - Manages conversations and uses the Response API with conversation context

### Voyage AI Classes

- **`VoyageEmbeddingsApi`** - Voyage embeddings with query/document optimization
- **`VoyageRerankApi`** - Voyage reranking service

## API Selection Guide

Choose the appropriate API implementation based on your needs:

| Use Case | Embeddings API | LLM API | Notes |
|----------|---------------|---------|--------|
| Simple RAG | OpenAIEmbeddingsApi | OpenAIResponseApi | Basic implementation without conversation history |
| Conversational RAG | OpenAIEmbeddingsApi | OpenAIConversationsApi | Maintains conversation context across requests |
| High-quality search | VoyageEmbeddingsApi | Any LLM API | Voyage provides specialized query/document embeddings |
| With reranking | Any Embeddings API | Any LLM API | Add VoyageRerankApi for relevance optimization |

## Basic Usage Examples

### Simple RAG with OpenAI Response API

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\AI\APIs\OpenAIResponseApi;
use Sigmie\Search\NewRagPrompt;

// Initialize APIs
$embeddings = new OpenAIEmbeddingsApi('your-openai-api-key');
$llm = new OpenAIResponseApi('your-openai-api-key');

// Set up Sigmie with embeddings
$sigmie = $this->sigmie->embedder($embeddings);

// Create a basic RAG query
$ragAnswer = $sigmie
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

// Access the answer
echo $ragAnswer->__toString();

// Access metadata
echo "Total tokens used: " . $ragAnswer->totalTokens() . "\n";
echo "Model: " . $ragAnswer->model() . "\n";
echo "Conversation ID: " . $ragAnswer->conversationId . "\n";

// Access search hits
foreach ($ragAnswer->hits as $hit) {
    echo "Source: {$hit['_source']['title']}\n";
}
```

### Conversational RAG with OpenAI Conversations API

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\AI\APIs\OpenAIConversationsApi;

$embeddings = new OpenAIEmbeddingsApi('your-openai-api-key');

// Create with new conversation
$llm = new OpenAIConversationsApi(
    apiKey: 'your-openai-api-key',
    conversationId: null, // Will create new
    metadata: ['project' => 'rag-demo'],
    model: 'gpt-5-nano'
);

// Or reuse existing conversation
$llm = new OpenAIConversationsApi(
    apiKey: 'your-openai-api-key',
    conversationId: 'conv_existing_id',
    metadata: [],
    model: 'gpt-5-nano'
);

$sigmie = $this->sigmie->embedder($embeddings);

$ragAnswer = $sigmie
    ->newRag($llm)
    ->search($searchBuilder)
    ->prompt(function (NewRagPrompt $prompt) {
        $prompt->question('What is the privacy policy?');
        $prompt->contextFields(['text', 'title']);
    })
    ->instructions("Be concise and precise")
    ->answer();

echo $ragAnswer->__toString();
echo "Conversation: {$ragAnswer->conversationId}\n";
```

### High-Quality Search with Voyage Embeddings and Reranking

```php
use Sigmie\AI\APIs\VoyageEmbeddingsApi;
use Sigmie\AI\APIs\OpenAIResponseApi;
use Sigmie\AI\APIs\VoyageRerankApi;
use Sigmie\Rag\NewRerank;

$embeddings = new VoyageEmbeddingsApi('your-voyage-api-key');
$llm = new OpenAIResponseApi('your-openai-api-key');
$reranker = new VoyageRerankApi('your-voyage-api-key');

$sigmie = $this->sigmie->embedder($embeddings);

$ragAnswer = $sigmie
    ->newRag($llm)
    ->reranker($reranker)
    ->search($searchBuilder)
    ->rerank(function (NewRerank $rerank) {
        $rerank->fields(['text', 'title']);
        $rerank->topK(3);
        $rerank->query('privacy policy');
    })
    ->prompt(function (NewRagPrompt $prompt) {
        $prompt->question('What is the privacy policy?');
        $prompt->contextFields(['text', 'title']);
    })
    ->answer();

echo $ragAnswer->__toString();
echo "Used {$ragAnswer->totalTokens()} tokens\n";
```

## RagAnswer Class

The `RagAnswer` class wraps the complete RAG response, providing access to both the search hits and the LLM answer.

### Properties

```php
class RagAnswer
{
    public readonly array $hits;                         // Array of \Sigmie\Document\Hit objects
    public readonly LLMAnswer|LLMJsonAnswer $llmAnswear; // The LLM answer
    public readonly ?string $conversationId;             // Optional conversation ID
}
```

### Methods

**`__toString(): string`**
Returns the LLM answer as a string.

```php
$ragAnswer = $rag->answer();
echo $ragAnswer; // Automatically calls __toString()
```

**`totalTokens(): int`**
Returns the total number of tokens used in the LLM response.

```php
$ragAnswer = $rag->answer();
echo "Tokens used: " . $ragAnswer->totalTokens();
```

**`model(): string`**
Returns the name of the LLM model used.

```php
$ragAnswer = $rag->answer();
echo "Model: " . $ragAnswer->model();
```

### Accessing Search Hits

The `$hits` property contains all the search hits that were used to generate the answer:

```php
$ragAnswer = $rag->answer();

// Iterate through hits
foreach ($ragAnswer->hits as $hit) {
    echo "Document ID: {$hit['_id']}\n";
    echo "Score: {$hit['_score']}\n";
    echo "Title: {$hit['_source']['title']}\n";
    echo "Content: {$hit['_source']['content']}\n";
}

// Get hit count
$hitCount = count($ragAnswer->hits);
echo "Used {$hitCount} documents";
```

### Token Usage Tracking

All RAG responses now include token usage information:

```php
$ragAnswer = $rag->answer();

// Get total tokens
$tokens = $ragAnswer->totalTokens();

// Example: Calculate cost
$costPerToken = 0.00002; // $0.02 per 1K tokens
$cost = ($tokens / 1000) * $costPerToken;
echo "Request cost: $" . number_format($cost, 4);
```

## Enhanced Streaming Events

The new architecture provides fine-grained control over the RAG pipeline with detailed streaming events. Here are all the events in order:

1. **`search_start`** - Starting document search
2. **`search_complete`** - Found X documents
3. **`search_hits`** - Provides access to the document hits array
4. **`rerank_start`** - Starting reranking (if enabled)
5. **`rerank_complete`** - Reranked to top K documents
6. **`prompt_generated`** - RAG prompt created
7. **`llm_request_start`** - LLM processing started
8. **`llm_chunk`** - Text chunks as they arrive
9. **`llm_complete`** - LLM response finished

### Event Details

**`search_hits` Event**

Emitted after `search_complete` and before `rerank_start`. Provides access to the document hits that were found.

```php
[
    'type' => 'search_hits',
    'data' => [...], // Array of \Sigmie\Document\Hit objects
    'timestamp' => 1234567890.1234
]
```

### Event-Driven Example

```php
$stream = $sigmie
    ->newRag($llm)
    ->search($searchBuilder)
    ->prompt(function (NewRagPrompt $prompt) {
        $prompt->question('What are renewable energy benefits?');
        $prompt->contextFields(['title', 'content']);
    })
    ->streamAnswer();

$searchHits = [];
$fullResponse = '';

foreach ($stream as $event) {
    switch ($event['type']) {
        case 'search_start':
            echo "Searching for documents...\n";
            break;
        case 'search_complete':
            echo "Found {$event['hits']} documents\n";
            break;
        case 'search_hits':
            // Access the document hits
            $searchHits = $event['data'];
            echo "Retrieved documents:\n";
            foreach ($searchHits as $hit) {
                echo "- {$hit['_source']['title']}\n";
            }
            break;
        case 'rerank_start':
            echo "Reranking documents...\n";
            break;
        case 'rerank_complete':
            echo "Reranked to {$event['hits']} documents\n";
            break;
        case 'prompt_generated':
            echo "Generated RAG prompt\n";
            break;
        case 'llm_request_start':
            echo "Generating response...\n";
            break;
        case 'llm_chunk':
            echo $event['data'];
            $fullResponse .= $event['data'];
            flush();
            break;
        case 'llm_complete':
            echo "\nResponse complete!\n";
            break;
    }
}

// You now have access to both the hits and the full response
echo "\n\nUsed " . count($searchHits) . " documents\n";
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
use Sigmie\AI\APIs\VoyageRerankApi;
use Sigmie\Rag\NewRerank;

$voyageReranker = new VoyageRerankApi('your-voyage-api-key');

$rag->reranker($voyageReranker)
    ->rerank(function (NewRerank $rerank) {
        $rerank->fields(['title', 'content']);
        $rerank->topK(5);  // Keep top 5 results after reranking
        $rerank->query('What is machine learning?');
    });
```

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

## Response Methods

### answer() - Non-Streaming Response

Get a complete `RagAnswer` object synchronously:

```php
$ragAnswer = $rag->answer();

// Access the answer text
echo $ragAnswer->__toString();
// or simply:
echo $ragAnswer;

// Get token usage
echo "Tokens: " . $ragAnswer->totalTokens() . "\n";

// Get model name
echo "Model: " . $ragAnswer->model() . "\n";

// Get conversation ID
echo "Conversation: " . $ragAnswer->conversationId . "\n";

// Access search hits
foreach ($ragAnswer->hits as $hit) {
    echo "Source: {$hit['_source']['title']}\n";
}
```

### jsonAnswer() - Structured JSON Response

Get a structured JSON response with schema validation:

```php
$ragAnswer = $rag
    ->jsonSchema([
        'type' => 'object',
        'properties' => [
            'answer' => ['type' => 'string'],
            'confidence' => ['type' => 'number'],
            'sources' => [
                'type' => 'array',
                'items' => ['type' => 'string']
            ]
        ],
        'required' => ['answer', 'confidence']
    ])
    ->jsonAnswer();

// Access the structured response
$json = json_decode($ragAnswer->__toString(), true);
echo "Answer: {$json['answer']}\n";
echo "Confidence: {$json['confidence']}\n";

// Still get token usage and hits
echo "Tokens: " . $ragAnswer->totalTokens() . "\n";
foreach ($ragAnswer->hits as $hit) {
    echo "Source: {$hit['_id']}\n";
}
```

### streamAnswer() - Real-Time Streaming

Stream the answer in real-time with structured events:

```php
$stream = $rag->streamAnswer();

$fullResponse = '';
$searchHits = [];

foreach ($stream as $event) {
    switch ($event['type']) {
        case 'search_start':
            echo "Searching...\n";
            break;
        case 'search_complete':
            echo "Found {$event['hits']} documents\n";
            break;
        case 'search_hits':
            // Store hits for later use
            $searchHits = $event['data'];
            echo "Retrieved:\n";
            foreach ($searchHits as $hit) {
                echo "- {$hit['_source']['title']}\n";
            }
            break;
        case 'rerank_complete':
            echo "Reranked to {$event['hits']} documents\n";
            break;
        case 'llm_chunk':
            // Stream text chunks in real-time
            echo $event['data'];
            $fullResponse .= $event['data'];
            flush();
            break;
        case 'llm_complete':
            echo "\nComplete!\n";
            break;
    }
}

// After streaming, you have both the full response and the hits
echo "\nFull answer length: " . strlen($fullResponse) . " chars\n";
echo "Based on " . count($searchHits) . " documents\n";
```

## Advanced Examples

### Semantic Search with Token Tracking

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\AI\APIs\OpenAIConversationsApi;
use Sigmie\AI\APIs\VoyageRerankApi;
use Sigmie\Mappings\NewProperties;

$embeddings = new OpenAIEmbeddingsApi('your-openai-api-key');
$llm = new OpenAIConversationsApi('your-openai-api-key');
$voyageReranker = new VoyageRerankApi('your-voyage-api-key');

// Set up semantic search properties
$props = new NewProperties;
$props->text('title')->semantic(accuracy: 1, dimensions: 256);
$props->text('content')->semantic(accuracy: 1, dimensions: 256);

$sigmie = $this->sigmie->embedder($embeddings);

$ragAnswer = $sigmie
    ->newRag($llm)
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

// Display the answer
echo $ragAnswer . "\n\n";

// Display metadata
echo "=== Metadata ===\n";
echo "Model: {$ragAnswer->model()}\n";
echo "Tokens: {$ragAnswer->totalTokens()}\n";
echo "Conversation: {$ragAnswer->conversationId}\n";
echo "Sources used: " . count($ragAnswer->hits) . "\n\n";

// Display sources
echo "=== Sources ===\n";
foreach ($ragAnswer->hits as $hit) {
    echo "- {$hit['_source']['title']} (score: {$hit['_score']})\n";
}
```

### Streaming with Full Pipeline Inspection

```php
$stream = $sigmie
    ->newRag($llm)
    ->reranker($voyageReranker)
    ->search($searchBuilder)
    ->rerank(function ($rerank) {
        $rerank->topK(3);
    })
    ->prompt(function ($prompt) {
        $prompt->question('How does photosynthesis work?');
        $prompt->contextFields(['title', 'content']);
    })
    ->streamAnswer();

$searchHits = [];
$fullAnswer = '';

foreach ($stream as $event) {
    switch ($event['type']) {
        case 'search_start':
            echo "=== Starting Search ===\n";
            break;
        case 'search_complete':
            echo "Found {$event['hits']} documents\n";
            break;
        case 'search_hits':
            $searchHits = $event['data'];
            echo "\nRetrieved Documents:\n";
            foreach ($searchHits as $i => $hit) {
                echo ($i + 1) . ". {$hit['_source']['title']} (score: {$hit['_score']})\n";
            }
            echo "\n";
            break;
        case 'rerank_start':
            echo "=== Reranking ===\n";
            break;
        case 'rerank_complete':
            echo "Reranked to top {$event['hits']} documents\n\n";
            break;
        case 'llm_request_start':
            echo "=== Generating Answer ===\n";
            break;
        case 'llm_chunk':
            echo $event['data'];
            $fullAnswer .= $event['data'];
            flush();
            break;
        case 'llm_complete':
            echo "\n\n=== Complete ===\n";
            echo "Answer length: " . strlen($fullAnswer) . " characters\n";
            echo "Based on " . count($searchHits) . " documents\n";
            break;
    }
}
```

### Multi-Index Research with Token Optimization

```php
$ragAnswer = $sigmie
    ->newRag($llm)
    ->multiSearch(function ($multiSearch) {
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
    })
    ->limits(maxTokens: 1000)
    ->answer();

// Display comprehensive results
echo $ragAnswer . "\n\n";

echo "=== Research Summary ===\n";
echo "Total sources: " . count($ragAnswer->hits) . "\n";
echo "Tokens used: " . $ragAnswer->totalTokens() . "\n";
echo "Model: " . $ragAnswer->model() . "\n\n";

echo "=== Sources by Type ===\n";
$byIndex = [];
foreach ($ragAnswer->hits as $hit) {
    $index = $hit['_index'];
    $byIndex[$index] = ($byIndex[$index] ?? 0) + 1;
}
foreach ($byIndex as $index => $count) {
    echo "{$index}: {$count} documents\n";
}
```

## Migration from Old OpenAILLM Class

If you're migrating from the old unified `OpenAILLM` class, here's how to update your code:

### Before (Old API)

```php
use Sigmie\AI\LLMs\OpenAILLM;

$llm = new OpenAILLM('your-openai-api-key');

$responses = $sigmie
    ->newRag($llm)
    ->search($searchBuilder)
    ->answer();
```

### After (New API)

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\AI\APIs\OpenAIResponseApi;

$embeddings = new OpenAIEmbeddingsApi('your-openai-api-key');
$llm = new OpenAIResponseApi('your-openai-api-key');

$sigmie = $this->sigmie->embedder($embeddings);

$ragAnswer = $sigmie
    ->newRag($llm)
    ->search($searchBuilder)
    ->answer();

// New: Access to hits and metadata
foreach ($ragAnswer->hits as $hit) {
    echo "Source: {$hit['_source']['title']}\n";
}
echo "Tokens: " . $ragAnswer->totalTokens() . "\n";
```

### For Conversational RAG

```php
use Sigmie\AI\APIs\OpenAIConversationsApi;

$llm = new OpenAIConversationsApi(
    apiKey: 'your-openai-api-key',
    conversationId: null, // Creates new conversation
    metadata: ['project' => 'my-app']
);

$ragAnswer = $rag->answer();
echo "Conversation ID: {$ragAnswer->conversationId}\n";
```

## Best Practices

### When to Use Different APIs

**Use OpenAIResponseApi When:**
- Building simple Q&A systems without conversation context
- Each request is independent
- You want the simplest implementation

**Use OpenAIConversationsApi When:**
- Building chatbots or conversational interfaces
- Context from previous messages is important
- You need conversation management features

**Use VoyageEmbeddingsApi When:**
- You need the highest quality semantic search
- Working with specialized domains
- Performance is critical

**Use VoyageRerankApi When:**
- You have more than 5 search results
- Cross-lingual search is important
- You need the best possible relevance

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

### Token Usage Optimization
- **Monitor token usage** using `totalTokens()` to track costs
- **Use shorter context fields** to reduce token consumption
- **Limit search results** to only what's necessary (fewer hits = fewer tokens)
- **Set maxTokens** appropriately to avoid unnecessarily long responses

### Performance Tips
- **Use streaming** for long responses to improve perceived performance
- **Cache frequent queries** at the application level
- **Monitor token usage** to optimize costs
- **Index optimization** - ensure your search indices are properly configured for your RAG queries

### Streaming Considerations
- **Access search hits early** using the `search_hits` event to display sources before the answer completes
- **Flush output buffers** regularly when displaying streaming content
- **Handle connection timeouts** gracefully in web applications
- **Process chunks immediately** to maintain streaming benefits
- **Implement proper error handling** for stream interruptions

## Error Handling

### Non-Streaming Error Handling
```php
try {
    $ragAnswer = $rag->answer();
    echo $ragAnswer;
    echo "Tokens: " . $ragAnswer->totalTokens() . "\n";
} catch (\RuntimeException $e) {
    if (str_contains($e->getMessage(), 'Search must be configured')) {
        echo "Please configure a search query first";
    }
} catch (\Exception $e) {
    echo "RAG query failed: " . $e->getMessage();
}
```

### Streaming Error Handling
```php
try {
    $stream = $rag->streamAnswer();

    foreach ($stream as $event) {
        if ($event['type'] === 'llm_chunk') {
            echo $event['data'];
            flush();
        }
    }
} catch (\RuntimeException $e) {
    if (str_contains($e->getMessage(), 'Search must be configured')) {
        echo "Please configure a search query first";
    }
} catch (\Exception $e) {
    echo "Streaming failed: " . $e->getMessage();
}
```

## Integration with Web Applications

### Server-Sent Events Streaming

```php
// Set headers for Server-Sent Events
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$stream = $rag->streamAnswer();

foreach ($stream as $event) {
    switch ($event['type']) {
        case 'search_complete':
            echo "data: " . json_encode([
                'type' => 'search',
                'hits' => $event['hits']
            ]) . "\n\n";
            break;
        case 'search_hits':
            // Send hits to client
            $hits = array_map(function($hit) {
                return [
                    'id' => $hit['_id'],
                    'title' => $hit['_source']['title'],
                    'score' => $hit['_score']
                ];
            }, $event['data']);
            echo "data: " . json_encode([
                'type' => 'sources',
                'hits' => $hits
            ]) . "\n\n";
            break;
        case 'rerank_complete':
            echo "data: " . json_encode([
                'type' => 'rerank',
                'hits' => $event['hits']
            ]) . "\n\n";
            break;
        case 'llm_chunk':
            echo "data: " . json_encode([
                'type' => 'delta',
                'content' => $event['data']
            ]) . "\n\n";
            break;
        case 'llm_complete':
            echo "data: " . json_encode(['type' => 'done']) . "\n\n";
            break;
    }
    flush();
}
```

### Token Usage Tracking for Billing

```php
// Track token usage for cost monitoring
$ragAnswer = $rag->answer();

$tokens = $ragAnswer->totalTokens();
$model = $ragAnswer->model();

// Example pricing (adjust for your actual costs)
$pricing = [
    'gpt-4' => ['input' => 0.03, 'output' => 0.06],
    'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
];

$cost = ($tokens / 1000) * $pricing[$model]['output'];

// Log for billing
file_put_contents('usage.log', json_encode([
    'timestamp' => time(),
    'conversation_id' => $ragAnswer->conversationId,
    'model' => $model,
    'tokens' => $tokens,
    'cost' => $cost,
    'hits' => count($ragAnswer->hits)
]) . "\n", FILE_APPEND);
```

### Security Considerations
- **Validate user input** before using in search queries
- **Implement rate limiting** for expensive RAG operations, especially streaming
- **Filter sensitive information** from context fields
- **Use appropriate API key permissions** for your LLM and reranking services
- **Monitor streaming connections** to prevent resource exhaustion
- **Track token usage** to prevent cost overruns
- **Log conversations** for audit trails while respecting privacy

This documentation covers the new modular API architecture where dedicated API classes handle specific responsibilities, providing developers with flexibility to choose the right combination of services for their RAG applications while maintaining full visibility into the pipeline through enhanced streaming events and the RagAnswer object.
