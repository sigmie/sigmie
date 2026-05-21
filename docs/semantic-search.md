---
title: Semantic Search
short_description: Vector embeddings, semantic fields, and similarity search
keywords: [semantic search, vector search, embeddings, ai, similarity]
category: Features
order: 1
related_pages: [search, rag, recommendations, opensearch, magic-tags]
---

# Semantic Search

Semantic search matches documents by **meaning**, not just keywords. "Portable computer for work" can match documents containing "laptop", "notebook", or "MacBook" — none of which share a word with the query.

Sigmie does this by:

1. Generating vector embeddings from your text at index time.
2. Generating an embedding for the query at search time.
3. Returning the documents whose vectors are most similar.

You bring an embeddings API (OpenAI, Cohere, Voyage, Infinity, or anything implementing `EmbeddingsApi`) and register it with the client:

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;

$sigmie->registerApi('embeddings', new OpenAIEmbeddingsApi('sk-...'));
```

The name `'embeddings'` is yours to choose — refer to it from your field definitions.

## Define a semantic field

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->title('title')->semantic(api: 'embeddings', dimensions: 1536);
$props->text('description')->semantic(api: 'embeddings', dimensions: 1536);

$sigmie->newIndex('articles')->properties($props)->create();
```

Match the `dimensions` to the model you registered. OpenAI's `text-embedding-3-small` outputs 1536-dim vectors; Infinity's `bge-small-en-v1.5` outputs 384-dim.

## Index documents

When a property has `->semantic()`, Sigmie generates embeddings automatically as documents flow through `merge()` and `add()`:

```php
use Sigmie\Document\Document;

$sigmie->collect('articles', refresh: true)
    ->properties($props)
    ->merge([
        new Document([
            'title' => 'Introduction to Machine Learning',
            'description' => 'A primer on supervised and unsupervised learning.',
        ]),
        new Document([
            'title' => 'Deep Learning Fundamentals',
            'description' => 'Neural networks form the basis of deep learning.',
        ]),
    ]);
```

## Search

Enable semantic matching with `->semantic()`:

```php
$response = $sigmie->newSearch('articles')
    ->properties($props)
    ->semantic()
    ->queryString('artificial intelligence basics')
    ->get();
```

By default this combines semantic and keyword matching. Documents matched by both rank higher than documents matched by only one.

### Pure semantic search

Drop keyword matching entirely:

```php
$sigmie->newSearch('articles')
    ->properties($props)
    ->semantic()
    ->disableKeywordSearch()
    ->queryString('machine learning algorithms')
    ->get();
```

### Score multipliers

Bias the blend between keyword and semantic scores:

```php
$sigmie->newSearch('articles')
    ->properties($props)
    ->semantic()
    ->textScoreMultiplier(1.0)
    ->semanticScoreMultiplier(2.0)        // emphasize semantic matches
    ->queryString('quantum computing')
    ->get();
```

## Embedding providers

Sigmie ships clients for several providers — all implement `Sigmie\AI\Contracts\EmbeddingsApi`:

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\AI\APIs\CohereEmbeddingsApi;
use Sigmie\AI\APIs\VoyageEmbeddingsApi;
use Sigmie\AI\APIs\InfinityEmbeddingsApi;

$sigmie->registerApi('embeddings', new OpenAIEmbeddingsApi('sk-...'));
$sigmie->registerApi('embeddings', new CohereEmbeddingsApi('co-...'));
$sigmie->registerApi('embeddings', new VoyageEmbeddingsApi('pa-...'));

// Local Infinity service (see Docker docs)
$sigmie->registerApi('embeddings', new InfinityEmbeddingsApi(
    baseUrl: 'http://localhost:7997',
    model: 'BAAI/bge-small-en-v1.5',
));
```

### Custom provider

Implement `EmbeddingsApi`:

```php
use Sigmie\AI\Contracts\EmbeddingsApi;
use GuzzleHttp\Promise\Promise;

class MyEmbeddings implements EmbeddingsApi
{
    public function embed(string $text, int $dimensions): array { /* ... */ }
    public function batchEmbed(array $payload): array { /* ... */ }
    public function promiseEmbed(string $text, int $dimensions): Promise { /* ... */ }
    public function model(): string { /* ... */ }
}

$sigmie->registerApi('embeddings', new MyEmbeddings());
```

## Accuracy

The `accuracy` parameter controls the HNSW index parameters under the hood. Higher accuracy means better recall at the cost of more memory and slower indexing:

```php
$props->text('content')->semantic(api: 'embeddings', dimensions: 512, accuracy: 1);
// Fast: m=16, ef_construction=80

$props->text('content')->semantic(api: 'embeddings', dimensions: 512, accuracy: 3);
// Balanced (default): m=64, ef_construction=300

$props->text('content')->semantic(api: 'embeddings', dimensions: 512, accuracy: 5);
// High quality: m=128, ef_construction=800

$props->text('content')->semantic(api: 'embeddings', dimensions: 512, accuracy: 7);
// Script-score: exact vectors, slowest, highest quality
```

Reach for higher accuracy on important fields (titles, primary content). Use lower accuracy on long, less-critical fields (tags, supporting text).

## Similarity functions

```php
use Sigmie\Enums\VectorSimilarity;

$props->text('content')->semantic(
    api: 'embeddings',
    dimensions: 512,
    similarity: VectorSimilarity::Cosine,            // default
);

// Other options:
VectorSimilarity::DotProduct;
VectorSimilarity::Euclidean;
VectorSimilarity::MaxInnerProduct;
```

- **Cosine** — standard for text similarity, handles different lengths.
- **Dot product** — efficient when your vectors are pre-normalized.
- **Euclidean** — distance-based, sensitive to magnitude.
- **Max inner product** — optimized for IP-similarity workloads.

## Multiple vectors per field

Index the same text with different similarity functions or accuracies:

```php
$props->text('job_description')
    ->semantic(
        api: 'embeddings',
        accuracy: 3,
        dimensions: 512,
        similarity: VectorSimilarity::Cosine,
    )
    ->semantic(
        api: 'embeddings',
        accuracy: 5,
        dimensions: 512,
        similarity: VectorSimilarity::Euclidean,
    );
```

## Field-specific semantic search

Restrict semantic matching to specific fields:

```php
$sigmie->newSearch('articles')
    ->properties($props)
    ->semantic()
    ->fields(['title'])
    ->queryString('deep learning neural networks')
    ->get();
```

## Working with arrays

Semantic fields work with array values — each entry is embedded independently:

```php
new Document([
    'experience' => [
        'Artist',
        'Graphic Design',
        'Creative Director',
    ],
]);

// "drawing illustration" matches "Artist" semantically
$sigmie->newSearch('professionals')
    ->semantic()
    ->properties($props)
    ->queryString('drawing illustration')
    ->get();
```

## Pre-computed embeddings

To skip Sigmie's embedding pipeline (for backfills or batched offline embedding), include vectors directly in the document:

```php
new Document([
    'title' => 'AI Research Paper',
    'content' => 'Artificial intelligence has evolved significantly...',
    '_embeddings' => [
        'title_vector' => [0.1, 0.2, 0.3, /* ... */],
        'content_vector' => [0.4, 0.5, 0.6, /* ... */],
    ],
]);
```

## Reranking

For higher-quality top-K results, rerank with a cross-encoder after retrieval:

```php
use Sigmie\AI\APIs\InfinityRerankApi;

$sigmie->registerApi('my-rerank', new InfinityRerankApi(
    baseUrl: 'http://localhost:7998',
    model: 'cross-encoder/ms-marco-MiniLM-L-6-v2',
));

$response = $sigmie->newSearch('articles')
    ->properties($props)
    ->semantic()
    ->queryString('quantum computing applications')
    ->size(20)
    ->get();

$top5 = $response->rerank('my-rerank', ['content'], topK: 5);
```

See [Retrieval and Agents](rag.md) for the full retrieval-then-generate pattern.

## Empty queries

By default, an empty query string returns every document. To return nothing instead:

```php
->noResultsOnEmptySearch()
```

## Multilingual

Embeddings handle most languages automatically. A query in English finds documents in French if the embedding model supports both:

```php
$sigmie->newSearch('documents')
    ->semantic()
    ->properties($props)
    ->queryString('machine learning')
    // matches "apprentissage automatique", "机器学习", etc.
    ->get();
```

## Patterns

### E-commerce search

```php
$props = new NewProperties;
$props->name('name')->semantic(api: 'embeddings', dimensions: 512);
$props->text('description')->semantic(api: 'embeddings', dimensions: 512);
$props->category('category');
$props->price();

$sigmie->newSearch('products')
    ->properties($props)
    ->semantic()
    ->queryString('comfortable running shoes')
    ->filters('price<=100')
    ->get();
```

### Content recommendation

```php
$sigmie->newSearch('articles')
    ->semantic()
    ->disableKeywordSearch()
    ->properties($props)
    ->queryString('machine learning deep learning neural networks')
    ->size(5)
    ->get();
```

For "similar items" using existing documents as seeds, see [Recommendations](recommendations.md) — it gives you RRF fusion and MMR diversification out of the box.

## Troubleshooting

**No results.** Check that documents have embeddings — index a sample and inspect with `$collection->get($id)`. If they don't, verify your `api:` name matches the registered API.

**Slow search.** Drop to lower accuracy or smaller dimensions. Add filters to narrow the candidate pool before vector ranking.

**Memory pressure.** Lower accuracy and dimensions. High accuracy with 1536-dim vectors needs serious RAM at scale.

## See also

- [Recommendations](recommendations.md) — "similar items" via vector retrieval with RRF and MMR.
- [Retrieval and Agents](rag.md) — combining retrieval, reranking, and generation.
- [Magic Tags](magic-tags.md) — LLM-generated taxonomy tags backed by embeddings.
- [Docker](docker.md) — running local Infinity embeddings and reranker.
