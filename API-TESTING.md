# API Testing with Fake APIs

The test suite includes spy/fake versions of all AI APIs that allow you to assert on method calls and parameters.

## Available Fake APIs

All tests automatically use these fake APIs:
- `FakeEmbeddingsApi` - Wraps `InfinityEmbeddingsApi`
- `FakeRerankApi` - Wraps `InfinityRerankApi`
- `FakeLLMApi` - Wraps `OllamaApi` (Ollama LLM)

## FakeEmbeddingsApi

### Available Assertions

```php
// Assert embed() was called (at least once)
$this->embeddingApi->assertEmbedWasCalled();

// Assert embed() was called exactly N times
$this->embeddingApi->assertEmbedWasCalled(3);

// Assert embed() was called with specific text
$this->embeddingApi->assertEmbedWasCalledWith('Hello World');

// Assert embed() was called with specific text and dimensions
$this->embeddingApi->assertEmbedWasCalledWith('Hello World', 384);

// Assert batchEmbed() was called
$this->embeddingApi->assertBatchEmbedWasCalled();

// Assert batchEmbed() was called exactly N times
$this->embeddingApi->assertBatchEmbedWasCalled(2);

// Assert batchEmbed() was called with N items
$this->embeddingApi->assertBatchEmbedWasCalledWithCount(5);
```

### Inspecting Calls

```php
// Get all embed() calls
$calls = $this->embeddingApi->getEmbedCalls();
// Returns: [['text' => '...', 'dimensions' => 384], ...]

// Get all batchEmbed() calls
$calls = $this->embeddingApi->getBatchEmbedCalls();
// Returns: [[{payload}], [{payload}], ...]
```

## FakeRerankApi

### Available Assertions

```php
// Assert rerank() was called
$this->rerankApi->assertRerankWasCalled();

// Assert rerank() was called exactly N times
$this->rerankApi->assertRerankWasCalled(1);

// Assert rerank() was called with specific query
$this->rerankApi->assertRerankWasCalledWith('search query');

// Assert rerank() was called with specific query and topK
$this->rerankApi->assertRerankWasCalledWith('search query', 5);

// Assert rerank() was called with N documents
$this->rerankApi->assertRerankWasCalledWithDocumentCount(10);
```

### Inspecting Calls

```php
// Get all rerank() calls
$calls = $this->rerankApi->getRerankCalls();
// Returns: [['documents' => [...], 'query' => '...', 'topK' => 5], ...]
```

## FakeLLMApi

### Available Assertions

```php
// Assert answer() was called
$this->llmApi->assertAnswerWasCalled();

// Assert answer() was called exactly N times
$this->llmApi->assertAnswerWasCalled(1);

// Assert streamAnswer() was called
$this->llmApi->assertStreamAnswerWasCalled();

// Assert streamAnswer() was called exactly N times
$this->llmApi->assertStreamAnswerWasCalled(2);

// Assert jsonAnswer() was called
$this->llmApi->assertJsonAnswerWasCalled();

// Assert jsonAnswer() was called exactly N times
$this->llmApi->assertJsonAnswerWasCalled(1);

// Assert a message with specific role and content was used
$this->llmApi->assertAnswerWasCalledWithMessage('user', 'substring of message');
$this->llmApi->assertAnswerWasCalledWithMessage('system', 'You are a helpful');
```

### Inspecting Calls

```php
// Get all answer() calls
$calls = $this->llmApi->getAnswerCalls();
// Returns: [['prompt' => Prompt, 'messages' => [...]], ...]

// Get all streamAnswer() calls
$calls = $this->llmApi->getStreamAnswerCalls();

// Get all jsonAnswer() calls
$calls = $this->llmApi->getJsonAnswerCalls();
// Returns: [['prompt' => Prompt, 'messages' => [...], 'schema' => [...]], ...]
```

## Example Test

```php
public function test_semantic_search_uses_correct_embeddings()
{
    $indexName = uniqid();

    $sigmie = $this->sigmie->embedder($this->embeddingApi);

    $blueprint = new NewProperties();
    $blueprint->text('title')->semantic(accuracy: 1, dimensions: 384);

    $sigmie->newIndex($indexName)->properties($blueprint)->create();

    $sigmie->collect($indexName, true)
        ->properties($blueprint)
        ->merge([
            new Document(['title' => 'Machine Learning']),
            new Document(['title' => 'Deep Learning']),
        ]);

    // Assert embeddings were generated
    $this->embeddingApi->assertBatchEmbedWasCalled();

    // Inspect what was sent
    $calls = $this->embeddingApi->getBatchEmbedCalls();
    $this->assertGreaterThan(0, count($calls));
}
```

## Resetting Between Tests

The fakes are automatically reset in `tearDown()`, so each test starts fresh with no recorded calls.

## Implementation Details

Each fake API:
1. Wraps the real API implementation
2. Records all method calls with parameters
3. Delegates to the real API to get actual responses
4. Provides assertion methods for testing

This means:
- ✅ Tests run against real services (no mocking responses)
- ✅ Can assert on what was called and with what parameters
- ✅ Failures show actual API behavior, not mock behavior
