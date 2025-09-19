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
$responses = $sigmie
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

// Get the RagResponse object
foreach ($responses as $ragResponse) {
    echo $ragResponse->finalAnswer();
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

$answer = $sigmie
    ->newRag($llm)
    ->search($searchBuilder)
    ->prompt(function (NewRagPrompt $prompt) {
        $prompt->question('What is the privacy policy?');
        $prompt->contextFields(['text', 'title']);
    })
    ->instructions("Be concise and precise")
    ->answer(stream: true);

foreach ($answer as $chunk) {
    if (is_array($chunk)) {
        // Handle events
        if ($chunk['type'] === 'conversation.created') {
            echo "Conversation: {$chunk['conversation_id']}\n";
        } elseif ($chunk['type'] === 'content.delta') {
            echo $chunk['delta'];
        }
    }
}
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

$answer = $sigmie
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
    ->answer(stream: false);

foreach ($answer as $ragResponse) {
    echo $ragResponse->finalAnswer();
}
```

## Enhanced Streaming Events

The new architecture provides fine-grained control over the RAG pipeline with detailed streaming events. Here are all the events in order:

1. **`conversation.created`** - Conversation was created with ID (OpenAIConversationsApi only)
2. **`search.started`** - Starting document search
3. **`search.completed`** - Found X documents
4. **`rerank.started`** - Starting reranking (if enabled)
5. **`rerank.completed`** - Reranked to top K documents
6. **`prompt.generated`** - RAG prompt created
7. **`stream.start`** - Response streaming begins with context
8. **`llm.request.started`** - LLM processing started
9. **`llm.first_token`** - First response token received
10. **`content.delta`** - Text chunks as they arrive
11. **`stream.complete`** - Streaming finished

### Event-Driven Example

```php
$stream = $sigmie
    ->newRag($llm)
    ->search($searchBuilder)
    ->prompt(function (NewRagPrompt $prompt) {
        $prompt->question('What are renewable energy benefits?');
        $prompt->contextFields(['title', 'content']);
    })
    ->answer(stream: true);

foreach ($stream as $event) {
    if (is_array($event)) {
        switch ($event['type']) {
            case 'conversation.created':
                echo "Created conversation: {$event['conversation_id']}\n";
                break;
            case 'search.started':
                echo "Searching for documents...\n";
                break;
            case 'search.completed':
                echo "Found {$event['metadata']['document_count']} documents\n";
                break;
            case 'rerank.started':
                echo "Reranking documents...\n";
                break;
            case 'rerank.completed':
                echo "Reranked to {$event['metadata']['reranked_count']} documents\n";
                break;
            case 'prompt.generated':
                echo "Generated RAG prompt\n";
                break;
            case 'stream.start':
                $context = $event['context'];
                echo "Starting response with {$context['retrieved_count']} documents\n";
                break;
            case 'llm.request.started':
                echo "Generating response...\n";
                break;
            case 'llm.first_token':
                echo "Response stream started\n";
                break;
            case 'content.delta':
                echo $event['delta'];
                flush();
                break;
            case 'stream.complete':
                echo "\nResponse complete!\n";
                break;
        }
    }
}
```

## Enhanced RagResponse Object

The `RagResponse` object has been enhanced to include conversation management:

### New Methods

```php
$responses = $rag->answer(stream: false);

foreach ($responses as $ragResponse) {
    // Get conversation ID (when using OpenAIConversationsApi)
    $conversationId = $ragResponse->conversationId();
    
    // Existing methods
    $answer = $ragResponse->finalAnswer();
    $retrievedDocs = $ragResponse->retrievedDocuments();
    $rerankedDocs = $ragResponse->rerankedDocuments();
    $hasReranking = $ragResponse->hasReranking();
    $prompt = $ragResponse->prompt();
    
    // Enhanced context with conversation ID
    $context = $ragResponse->context();
    // Returns:
    // [
    //     'retrieved_count' => 5,
    //     'reranked_count' => 3,
    //     'has_reranking' => true,
    //     'documents' => [...],
    //     'conversation_id' => 'conv_abc123'
    // ]
    
    // Enhanced toArray output
    $array = $ragResponse->toArray();
    // Includes conversation_id in output
}
```

## Conversation Management

The `OpenAIConversationsApi` provides methods for conversation management:

### Creating and Reusing Conversations

```php
// Create new conversation
$api = new OpenAIConversationsApi($apiKey);

// Get current conversation ID
$conversationId = $api->conversation();

// Access metadata
$metadata = $api->metadata(); // Returns ['conversation' => $id, 'model' => $model]

// Reuse existing conversation
$existingApi = new OpenAIConversationsApi(
    apiKey: $apiKey,
    conversationId: 'conv_existing_123'
);
```

### Conversation Context in RAG

```php
$llm = new OpenAIConversationsApi($apiKey);

$ragResponse = $sigmie
    ->newRag($llm)
    ->search($searchBuilder)
    ->prompt(function (NewRagPrompt $prompt) {
        $prompt->question('Follow-up question about previous topic');
        $prompt->contextFields(['text']);
    })
    ->answer(stream: false);

foreach ($ragResponse as $response) {
    // Conversation ID is automatically included
    $conversationId = $response->conversationId();
    
    // Use this ID for subsequent requests to maintain context
    $followUpLlm = new OpenAIConversationsApi(
        apiKey: $apiKey,
        conversationId: $conversationId
    );
}
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

### answer() - Unified Streaming and Non-Streaming API

The `answer()` method provides a unified interface for both streaming and non-streaming responses through the `stream` parameter:

#### Non-Streaming Response (Default)

Get complete RagResponse objects synchronously:

```php
$responses = $rag->answer(stream: false);
// or simply: $responses = $rag->answer();

foreach ($responses as $ragResponse) {
    // Access the final answer
    $answer = $ragResponse->finalAnswer();
    
    // Get conversation ID (if using OpenAIConversationsApi)
    $conversationId = $ragResponse->conversationId();
    
    // Get context information
    $context = $ragResponse->context();
    echo "Retrieved: {$context['retrieved_count']} documents\n";
    
    if ($context['has_reranking']) {
        echo "Reranked to: {$context['reranked_count']} documents\n";
    }
    
    // Access retrieved documents
    foreach ($ragResponse->retrievedDocuments() as $doc) {
        echo "Source: {$doc['title']}\n";
    }
    
    echo "Answer: {$answer}\n";
}
```

#### Streaming Response

Stream the answer in real-time with structured events:

```php
$stream = $rag->answer(stream: true);

$fullResponse = '';
$context = null;
$conversationId = null;

foreach ($stream as $chunk) {
    if (is_array($chunk)) {
        switch ($chunk['type']) {
            case 'conversation.created':
                $conversationId = $chunk['conversation_id'];
                echo "Conversation: {$conversationId}\n";
                break;
            case 'search.completed':
                echo "Found {$chunk['metadata']['document_count']} documents\n";
                break;
            case 'stream.start':
                // Initial context with retrieved and reranked docs
                $context = $chunk['context'];
                echo "Processing {$context['retrieved_count']} documents...\n";
                if ($context['has_reranking']) {
                    echo "Reranked to {$context['reranked_count']} documents\n";
                }
                break;
            case 'content.delta':
                // Stream text chunks in real-time
                echo $chunk['delta'];
                $fullResponse .= $chunk['delta'];
                flush();
                break;
            case 'stream.complete':
                echo "\nComplete!\n";
                break;
        }
    }
}
```

## Advanced Examples

### Semantic Search with Streaming and Pipeline Inspection

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

$stream = $sigmie
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
    ->answer(stream: true);

// Stream the response with detailed logging
foreach ($stream as $chunk) {
    if (is_array($chunk)) {
        switch ($chunk['type']) {
            case 'conversation.created':
                echo "=== Conversation {$chunk['conversation_id']} ===\n";
                break;
            case 'stream.start':
                $context = $chunk['context'];
                echo "=== RAG Pipeline Started ===\n";
                echo "Retrieved: {$context['retrieved_count']} documents\n";
                echo "Reranked: {$context['reranked_count']} documents\n";
                echo "Sources:\n";
                foreach ($context['documents'] as $doc) {
                    echo "- {$doc['title']}\n";
                }
                echo "\n=== Generating Answer ===\n";
                break;
            case 'content.delta':
                echo $chunk['delta'];
                flush();
                break;
            case 'stream.complete':
                echo "\n\n=== Generation Complete ===\n";
                break;
        }
    }
}
```

### Multi-Index Research with Full Context Access

```php
$stream = $sigmie
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
    ->answer(stream: true);

foreach ($stream as $chunk) {
    if (is_array($chunk)) {
        if ($chunk['type'] === 'stream.start') {
            $context = $chunk['context'];
            echo "Analyzed {$context['retrieved_count']} documents from multiple sources\n";
        } elseif ($chunk['type'] === 'content.delta') {
            echo $chunk['delta'];
            flush();
        }
    }
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

$responses = $sigmie
    ->newRag($llm)
    ->search($searchBuilder)
    ->answer();
```

### For Conversational RAG

```php
use Sigmie\AI\APIs\OpenAIConversationsApi;

$llm = new OpenAIConversationsApi(
    apiKey: 'your-openai-api-key',
    conversationId: null, // Creates new conversation
    metadata: ['project' => 'my-app']
);
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

### Performance Tips
- **Use streaming** for long responses to improve perceived performance
- **Cache frequent queries** at the application level
- **Monitor token usage** to optimize costs
- **Index optimization** - ensure your search indices are properly configured for your RAG queries

### Streaming Considerations
- **Flush output buffers** regularly when displaying streaming content
- **Handle connection timeouts** gracefully in web applications
- **Process chunks immediately** to maintain streaming benefits
- **Implement proper error handling** for stream interruptions

## Error Handling

### Non-Streaming Error Handling
```php
try {
    $responses = $rag->answer(stream: false);
    foreach ($responses as $ragResponse) {
        echo $ragResponse->finalAnswer();
    }
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
    $stream = $rag->answer(stream: true);
    
    foreach ($stream as $chunk) {
        if (is_array($chunk) && $chunk['type'] === 'content.delta') {
            echo $chunk['delta'];
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

$stream = $rag->answer(stream: true);

foreach ($stream as $chunk) {
    if (is_array($chunk)) {
        switch ($chunk['type']) {
            case 'conversation.created':
                echo "data: " . json_encode([
                    'type' => 'conversation',
                    'conversation_id' => $chunk['conversation_id']
                ]) . "\n\n";
                break;
            case 'stream.start':
                echo "data: " . json_encode([
                    'type' => 'context',
                    'retrieved_count' => $chunk['context']['retrieved_count'],
                    'has_reranking' => $chunk['context']['has_reranking']
                ]) . "\n\n";
                break;
            case 'content.delta':
                echo "data: " . json_encode([
                    'type' => 'delta',
                    'content' => $chunk['delta']
                ]) . "\n\n";
                break;
            case 'stream.complete':
                echo "data: " . json_encode(['type' => 'done']) . "\n\n";
                break;
        }
        flush();
    }
}
```

### Security Considerations
- **Validate user input** before using in search queries
- **Implement rate limiting** for expensive RAG operations, especially streaming
- **Filter sensitive information** from context fields
- **Use appropriate API key permissions** for your LLM and reranking services
- **Monitor streaming connections** to prevent resource exhaustion
- **Log RagResponse context** for audit trails while respecting privacy

This documentation covers the new modular API architecture where dedicated API classes handle specific responsibilities, providing developers with flexibility to choose the right combination of services for their RAG applications while maintaining full visibility into the pipeline through enhanced streaming events and the RagResponse object.