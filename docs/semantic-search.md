---
title: Semantic Search
short_description: Build AI-powered semantic search with vector embeddings
keywords: [semantic search, vector search, embeddings, ai, machine learning, similarity]
category: Features
order: 1
related_pages: [search, rag, recommendations, opensearch]
---

# Semantic Search & AI Features

Sigmie provides powerful semantic search capabilities using vector embeddings and AI-powered features that go beyond traditional keyword-based search.

## Introduction

Semantic search allows you to find documents based on meaning and context rather than just exact keyword matches. This is particularly useful for:

- Finding similar content even with different wording
- Handling synonyms and related concepts naturally  
- Improving search relevance for natural language queries
- Creating recommendation systems
- Multilingual search capabilities

## Setting Up Semantic Fields

### Basic Semantic Field

To enable semantic search on a field, simply add the `semantic()` method:

```php
use Sigmie\Mappings\NewProperties;

$properties = new NewProperties;
$properties->title('title')->semantic();
$properties->text('description')->semantic();
$properties->shortText('tags')->semantic();

$sigmie->newIndex('articles')
    ->properties($properties)
    ->create();
```

### Advanced Semantic Configuration

You can fine-tune semantic fields with various parameters:

```php
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;

// Configure accuracy and dimensions
$properties->text('content')
    ->semantic(accuracy: 3, dimensions: 512);

// Choose similarity function
$properties->text('description')
    ->semantic(similarity: VectorSimilarity::Cosine);

// Different similarity options
$properties->text('title')
    ->semantic(similarity: VectorSimilarity::Euclidean);

$properties->text('summary') 
    ->semantic(similarity: VectorSimilarity::DotProduct);

$properties->text('abstract')
    ->semantic(similarity: VectorSimilarity::MaxInnerProduct);
```

### Vector Strategy Options

Control how vectors are used in search:

```php
use Sigmie\Enums\VectorStrategy;

// Default HNSW (Hierarchical Navigable Small World)
$properties->text('content')->semantic();

// Script score strategy for more control
$properties->shortText('experience')
    ->semantic()
    ->vectorStrategy(VectorStrategy::ScriptScore);

// Concatenate strategy (requires elastiknn plugin)
$properties->title('title')
    ->semantic()
    ->vectorStrategy(VectorStrategy::Concatenate);
```

### Accuracy Levels

Accuracy affects the balance between search quality and performance:

```php
// Accuracy 1 (fastest, less accurate)
$properties->text('content')->semantic(accuracy: 1);
// Results in: m=16, ef_construction=80, dims=256

// Accuracy 3 (balanced)
$properties->text('content')->semantic(accuracy: 3);  
// Results in: m=64, ef_construction=300, dims=256

// Accuracy 5 (high quality)
$properties->text('content')->semantic(accuracy: 5);
// Results in: m=64, ef_construction=400, dims=256

// Accuracy 7 (script score for highest quality)
$properties->text('content')->semantic(accuracy: 7);
// Uses script score strategy with exact vectors
```

### Multiple Vector Representations

You can have multiple vector representations for the same field:

```php
$properties->text('job_description')
    ->semantic(accuracy: 3, dimensions: 512, similarity: VectorSimilarity::Cosine)
    ->semantic(accuracy: 5, dimensions: 512, similarity: VectorSimilarity::Euclidean);
```

### Advanced Semantic Field Builder

For more control, use the `NewSemanticField` builder:

```php
use Sigmie\Mappings\NewSemanticField;

$properties->text('content')
    ->newSemantic(function (NewSemanticField $semantic) {
        $semantic->cosineSimilarity();
        // Other options:
        // $semantic->euclideanSimilarity();
        // $semantic->dotProductSimilarity();
        // $semantic->maxInnerProductSimilarity();
    });
```

## Using Semantic Search

### Basic Semantic Search

Enable semantic search in your queries:

```php
$response = $sigmie->newSearch('articles')
    ->semantic()
    ->properties($properties)
    ->queryString('artificial intelligence machine learning')
    ->get();

$hits = $response->hits();
```

### Combining Keyword and Semantic Search

By default, Sigmie combines both keyword and semantic search for best results:

```php
$response = $sigmie->newSearch('articles')
    ->semantic()  // Enable semantic search
    ->properties($properties)
    ->queryString('AI technology')  // Will use both keyword and vector search
    ->fields(['title', 'description'])
    ->get();
```

### Semantic-Only Search

Disable keyword search to rely purely on semantic matching:

```php
$response = $sigmie->newSearch('articles') 
    ->semantic()
    ->disableKeywordSearch()  // Pure semantic search
    ->properties($properties)
    ->queryString('machine learning algorithms')
    ->get();
```

### Handling Empty Queries

Control behavior when query string is empty:

```php
$response = $sigmie->newSearch('articles')
    ->semantic()
    ->noResultsOnEmptySearch()  // Return no results for empty queries
    ->properties($properties) 
    ->queryString('')
    ->get();
```

## Document Indexing with Embeddings

### Automatic Embedding Generation

When you index documents with semantic fields, Sigmie automatically generates embeddings:

```php
$documents = $sigmie->collect('articles', refresh: true)
    ->properties($properties)  // Properties with semantic fields
    ->merge([
        new Document([
            'title' => 'Introduction to Machine Learning',
            'content' => 'Machine learning is a subset of artificial intelligence...',
        ]),
        new Document([
            'title' => 'Deep Learning Fundamentals', 
            'content' => 'Neural networks form the basis of deep learning...',
        ]),
    ]);
```

### Working with Pre-computed Embeddings

If you have pre-computed embeddings, you can include them directly:

```php
new Document([
    'title' => 'AI Research Paper',
    'content' => 'Artificial intelligence has evolved significantly...',
    'embeddings' => [
        'title_vector' => [0.1, 0.2, 0.3, ...], // Your pre-computed vectors
        'content_vector' => [0.4, 0.5, 0.6, ...],
    ]
]);
```

## AI Providers

Sigmie supports different AI providers for generating embeddings:

### Sigmie AI (Default)

```php
use Sigmie\Semantic\Providers\SigmieAI;

// Default provider - automatically configured
$response = $sigmie->newSearch('articles')
    ->semantic()
    ->properties($properties)
    ->queryString('natural language processing')
    ->get();
```

### No-op Provider (for Testing)

```php
use Sigmie\Semantic\Providers\Noop;

// For testing - doesn't generate real embeddings
$noop = new Noop();
```

### Custom AI Providers

You can implement custom AI providers by extending the `AbstractAIProvider`:

```php
use Sigmie\Semantic\Providers\AbstractAIProvider;
use Sigmie\Semantic\Contracts\AIProvider;

class CustomAIProvider extends AbstractAIProvider implements AIProvider
{
    public function embeddings(array $texts): array
    {
        // Your custom embedding logic
        return $this->callCustomEmbeddingAPI($texts);
    }
}
```

## Advanced Features

### Reranking

Improve search results with AI-powered reranking:

```php
use Sigmie\Semantic\Reranker;

$response = $sigmie->newSearch('articles')
    ->semantic()
    ->properties($properties)
    ->queryString('quantum computing applications')
    ->get();

// Apply reranking
$reranker = new Reranker();
$rerankedResults = $reranker->rerank($response, $queryString);
```

### Handling Arrays and Multiple Values

Semantic fields work with array values:

```php
new Document([
    'experience' => [
        'Artist',
        'Graphic Design', 
        'Creative Director'
    ],
]);

// Search will find semantically related terms
$response = $sigmie->newSearch('professionals')
    ->semantic() 
    ->properties($properties)
    ->queryString('drawing illustration')  // Finds "Artist"
    ->get();
```

### Field-Specific Semantic Search

Limit semantic search to specific fields:

```php
$response = $sigmie->newSearch('articles')
    ->semantic()
    ->properties($properties)
    ->fields(['title'])  // Only search in title semantically
    ->queryString('deep learning neural networks')
    ->get();
```

## Plugin Requirements

Some advanced features require additional Elasticsearch plugins:

### Elastiknn Plugin

For certain vector strategies:

```php
use Sigmie\Sigmie;

// Register plugins
Sigmie::registerPlugins([
    'elastiknn'
]);

// Now you can use Concatenate strategy
$properties->title('title')
    ->semantic()
    ->vectorStrategy(VectorStrategy::Concatenate);
```

### Checking Plugin Availability

Test for plugin availability in your tests:

```php
$this->skipIfElasticsearchPluginNotInstalled('elastiknn');
```

## Vector Similarity Functions

### Cosine Similarity (Default)
Best for general text similarity, handles different text lengths well:

```php
$properties->text('content')->semantic(similarity: VectorSimilarity::Cosine);
```

### Euclidean Distance (L2 Norm)
Good for precise distance measurements:

```php
$properties->text('content')->semantic(similarity: VectorSimilarity::Euclidean);
```

### Dot Product
Efficient for normalized vectors:

```php
$properties->text('content')->semantic(similarity: VectorSimilarity::DotProduct);
```

### Max Inner Product
Optimized for maximum inner product similarity:

```php
$properties->text('content')->semantic(similarity: VectorSimilarity::MaxInnerProduct);
```

## Performance Considerations

### Index Parameters

The accuracy level affects these HNSW parameters:

- **m**: Number of bi-directional links for each node
- **ef_construction**: Size of the dynamic candidate list
- **dims**: Vector dimensions (typically 256, 512, or 768)

```php
// Low accuracy = faster indexing, less memory
$properties->text('content')->semantic(accuracy: 1);  // m=16, ef_construction=80

// High accuracy = better quality, more resources  
$properties->text('content')->semantic(accuracy: 5);  // m=128, ef_construction=800
```

### Memory Usage

Higher accuracy levels use more memory:

```php
// Memory efficient
$properties->text('content')->semantic(accuracy: 1, dimensions: 256);

// More memory intensive
$properties->text('content')->semantic(accuracy: 5, dimensions: 768);
```

### Search Speed vs Quality

Use script score for highest quality when search speed is less critical:

```php
$properties->text('content')->semantic(accuracy: 7);  // Uses script score
```

## Common Patterns

### E-commerce Product Search

```php
$properties = new NewProperties;
$properties->name('name')->semantic();
$properties->text('description')->semantic();
$properties->tags('features')->semantic();
$properties->category('category');
$properties->price('price');

$response = $sigmie->newSearch('products')
    ->semantic()
    ->properties($properties)
    ->queryString('comfortable running shoes')  // Finds semantically similar products
    ->filters('price<=100')
    ->get();
```

### Content Recommendation

```php
$properties = new NewProperties;
$properties->title('title')->semantic();  
$properties->longText('content')->semantic(accuracy: 3);
$properties->tags('tags')->semantic();

// Find similar articles
$response = $sigmie->newSearch('articles')
    ->semantic()
    ->disableKeywordSearch()  // Pure similarity search
    ->properties($properties)
    ->queryString('machine learning deep learning neural networks')
    ->size(5)
    ->get();
```

### Multilingual Search

```php
$properties = new NewProperties;
$properties->text('content')->semantic();  // Embeddings handle language naturally

$response = $sigmie->newSearch('documents')
    ->semantic()
    ->properties($properties)
    ->queryString('machine learning')  // Finds "apprentissage automatique", "机器学习", etc.
    ->get();
```

### Job Matching

```php
$properties = new NewProperties;
$properties->shortText('skills')->semantic();
$properties->longText('experience')->semantic();
$properties->text('job_description')->semantic(accuracy: 3, dimensions: 512);

$response = $sigmie->newSearch('candidates')
    ->semantic()
    ->properties($properties)
    ->queryString('python data science machine learning')
    ->get();
```

## Troubleshooting

### Empty Results with Semantic Search

If semantic search returns no results:

1. Check if embeddings are being generated
2. Verify AI provider configuration  
3. Use keyword search as fallback
4. Check for empty query handling

```php
$response = $sigmie->newSearch('articles')
    ->semantic()
    ->properties($properties)
    ->queryString($query ?: 'fallback query')  // Prevent empty queries
    ->get();

if ($response->total() === 0) {
    // Fallback to keyword search
    $response = $sigmie->newSearch('articles')
        ->properties($properties)
        ->queryString($query)
        ->get();
}
```

### Performance Issues

1. Lower accuracy for better performance
2. Reduce vector dimensions
3. Use appropriate similarity function
4. Consider batch operations

```php
// Performance optimized
$properties->text('content')->semantic(accuracy: 1, dimensions: 256);
```

### Memory Errors

1. Reduce accuracy level
2. Use smaller dimensions
3. Implement proper index management

```php
// Memory efficient configuration
$properties->text('content')->semantic(
    accuracy: 2, 
    dimensions: 256, 
    similarity: VectorSimilarity::Cosine
);
```

## Best Practices

1. **Start Simple**: Begin with basic semantic fields and default settings
2. **Test Accuracy Levels**: Experiment with different accuracy levels for your use case
3. **Monitor Performance**: Watch memory usage and search latency
4. **Combine Strategies**: Use both keyword and semantic search for best results
5. **Field Selection**: Apply semantic search to the most important fields
6. **Batch Indexing**: Index documents in batches for better performance
7. **Error Handling**: Always handle empty queries and fallback scenarios

```php
// Well-balanced configuration
$properties = new NewProperties;
$properties->title('title')->semantic(accuracy: 3);  // Important field, good accuracy
$properties->text('content')->semantic(accuracy: 2);  // Large field, moderate accuracy
$properties->tags('tags')->semantic(accuracy: 1);     // Many values, fast processing

$response = $sigmie->newSearch('articles')
    ->semantic()
    ->noResultsOnEmptySearch()
    ->properties($properties)
    ->queryString($userQuery)
    ->get();
```