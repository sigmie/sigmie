---
title: Semantic Search
short_description: Add semantic vector search to Elasticsearch with Sigmie. Register an embeddings API, define semantic fields, tune accuracy and index type, then rerank.
keywords: [semantic search, vector search, embeddings, ai, similarity]
category: Features
order: 1
related_pages: [search, rag, recommendations, opensearch, magic-tags]
---

# Semantic Search

Semantic search matches documents by meaning. The query "portable computer for work" finds a document about a "laptop" even though the two share no word.

Sigmie embeds the text of each semantic field when you index a document, embeds the query when you search, and returns the documents whose vectors are closest to the query vector. This page shows how to set that up and tune it.

## Register an embeddings API

Register a client for your embeddings provider under a name of your choice:

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;

$sigmie->registerApi('embeddings', new OpenAIEmbeddingsApi('sk-...')); // [tl! highlight]
```

Your field definitions refer to the API by this name. Sigmie ships these clients, and all of them implement `Sigmie\AI\Contracts\EmbeddingsApi`:

```php
use Sigmie\AI\APIs\CohereEmbeddingsApi;
use Sigmie\AI\APIs\InfinityEmbeddingsApi;
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\AI\APIs\VoyageEmbeddingsApi;
use Sigmie\Enums\CohereInputType;

$sigmie->registerApi('embeddings', new OpenAIEmbeddingsApi('sk-...'));
$sigmie->registerApi('embeddings', new CohereEmbeddingsApi('co-...', CohereInputType::SearchDocument));
$sigmie->registerApi('embeddings', new VoyageEmbeddingsApi('pa-...'));

// Local Infinity service (see Docker docs)
$sigmie->registerApi('embeddings', new InfinityEmbeddingsApi(
    baseUrl: 'http://localhost:7997',
    model: 'BAAI/bge-small-en-v1.5',
));
```

To run a local Infinity service, see [Docker](docker.md).

### Use another provider

To use a provider that Sigmie has no client for, implement `EmbeddingsApi` and register your class:

```php
use GuzzleHttp\Promise\Promise;
use Sigmie\AI\Contracts\EmbeddingsApi;

class MyEmbeddings implements EmbeddingsApi
{
    public function embed(string $text, int $dimensions): array { /* ... */ }
    public function batchEmbed(array $payload): array { /* ... */ }
    public function promiseEmbed(string $text, int $dimensions): Promise { /* ... */ }
    public function model(): string { /* ... */ }
    public function maxBatchSize(): int { /* ... */ }
}

$sigmie->registerApi('embeddings', new MyEmbeddings());
```

`maxBatchSize()` returns the number of texts your provider accepts in one `batchEmbed()` call.

## Define semantic fields

Call `semantic()` on a text field. Set `api` to the registered name and `dimensions` to the vector size of your model:

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->title('title')->semantic(api: 'embeddings', dimensions: 1536); // [tl! highlight]
$props->text('description')->semantic(api: 'embeddings', dimensions: 1536);

$sigmie->newIndex('articles')->properties($props)->create();
```

OpenAI's `text-embedding-3-small` returns 1536 dimensions. Infinity's `bge-small-en-v1.5` returns 384. `dimensions` defaults to 256. `semantic()` accepts 128, 256, 384, 512, 1024, 1536, 2048, or 3072 and throws for any other value. For another size, set it with `dimensions()` in [`newSemantic()`](#fine-tune-the-vector-field).

## Index documents

Add documents with `merge()` or `add()`. Sigmie calls the embeddings API for every semantic field before it writes the documents:

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

`merge()` sends the texts of many documents in batched requests, not one request per document. Sigmie groups texts that share the same API, dimensions, and input type (text or image), then splits each group into chunks of `min(100, maxBatchSize())` texts:

```php
$articles->merge($thirtyDocs);     // 1 request of 30
$articles->merge($twoHundredDocs); // 2 requests: 100 + 100
```

The provider limits are 2048 for OpenAI and Jina, 512 for Infinity, 128 for Voyage, and 96 for Cohere.

> **Warning:** If one chunk fails, the whole `merge()` fails. Sigmie does not retry or write part of the batch. Keep each `merge()` call to a size your provider serves reliably.

## Search by meaning

Call `semantic()` on the search:

```php
$response = $sigmie->newSearch('articles')
    ->properties($props)
    ->semantic() // [tl! highlight]
    ->queryString('artificial intelligence basics')
    ->get();
```

The search runs the keyword query and the vector query together. A document that matches both ranks above a document that matches only one.

To rank by vectors only, turn off the keyword query:

```php
$sigmie->newSearch('articles')
    ->properties($props)
    ->semantic()
    ->disableKeywordSearch() // [tl! highlight]
    ->queryString('machine learning algorithms')
    ->get();
```

To give one side more weight, multiply its score. Both multipliers default to `1.0`:

```php
$sigmie->newSearch('articles')
    ->properties($props)
    ->semantic()
    ->textScoreMultiplier(1.0)
    ->semanticScoreMultiplier(2.0) // [tl! highlight]
    ->queryString('quantum computing')
    ->get();
```

To search some semantic fields and skip the others, list them with `fields()`:

```php
$sigmie->newSearch('articles')
    ->properties($props)
    ->semantic()
    ->fields(['title']) // [tl! highlight]
    ->queryString('deep learning neural networks')
    ->get();
```

Filters, sorting, and facets work with semantic search the same way they work with keyword search. See [Search](search.md). A multilingual embedding model also matches across languages, so the query "machine learning" finds "apprentissage automatique".

## Choose an accuracy level

`accuracy` goes from 1 to 7 and defaults to 3. It sets how Sigmie embeds a field and the HNSW graph parameters `m` and `ef_construction`. A higher level gives better recall and costs more memory and indexing time:

```php
$props->text('content')->semantic(api: 'embeddings', dimensions: 512, accuracy: 5); // [tl! highlight]
```

| Accuracy | Embedding | `m` at 256 dims | `ef_construction` at 256 dims |
|----------|-----------|-----------------|-------------------------------|
| 1 | One vector for all values joined | 12 | 60 |
| 2 | One vector per value, averaged | 16 | 100 |
| 3 (default) | One vector per value, averaged | 24 | 150 |
| 4 | One vector per value, averaged | 32 | 200 |
| 5 | One vector per value, averaged | 40 | 300 |
| 6 | One vector per value, averaged | 48 | 400 |
| 7 | One vector per value, exact script score, no HNSW graph | none | none |

For other dimensions, Sigmie multiplies both values by `sqrt(dimensions / 256)`, then caps `m` at 64 and `ef_construction` at 500. Accuracy 3 at 1536 dimensions gives `m` 59 and `ef_construction` 367.

Use a high level on the fields that carry the meaning, such as titles and main content. Use a low level on long supporting text.

## Search array values

A semantic field can hold an array. At accuracy 2 and above, Sigmie embeds each value on its own. At accuracy 1, it joins the values into one string and embeds that:

```php
new Document([
    'experience' => [
        'Artist',
        'Graphic Design',
        'Creative Director',
    ],
]);

// "drawing illustration" matches "Artist"
$sigmie->newSearch('professionals')
    ->properties($props)
    ->semantic()
    ->queryString('drawing illustration')
    ->get();
```

## Fine-tune the vector field

For settings that `semantic()` does not take, call `newSemantic()` and configure a `NewSemanticField`:

```php
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Mappings\NewSemanticField;

$props->text('content')->newSemantic(function (NewSemanticField $semantic): void {
    $semantic->api('embeddings')
        ->accuracy(3, 1024)
        ->similarity(VectorSimilarity::DotProduct); // [tl! highlight]
});
```

`semantic()` returns the same `NewSemanticField`, so you can also chain onto it: `->semantic(api: 'embeddings', dimensions: 512)->euclideanSimilarity()`.

### Similarity

Similarity defaults to cosine. Set it with `similarity()` or a shortcut method:

| `VectorSimilarity` case | Shortcut | Elasticsearch value |
|-------------------------|----------|---------------------|
| `Cosine` | `cosineSimilarity()` | `cosine` |
| `DotProduct` | `dotProductSimilarity()` | `dot_product` |
| `Euclidean` | `euclideanSimilarity()` | `l2_norm` |
| `MaxInnerProduct` | `maxInnerProductSimilarity()` | `max_inner_product` |

Cosine fits most text models. Dot product needs normalized vectors, and Sigmie normalizes vectors by default.

### Index type

Sigmie indexes vectors as float `hnsw` by default. On Elasticsearch, a quantized index type keeps a compressed copy of each vector in memory:

```php
$props->text('content')->newSemantic(function (NewSemanticField $semantic): void {
    $semantic->api('embeddings')->dimensions(768)->indexType('bbq_hnsw'); // [tl! highlight]
});
```

The allowed types are `hnsw`, `int8_hnsw`, `int4_hnsw`, `bbq_hnsw`, `flat`, `int8_flat`, `int4_flat`, and `bbq_flat`. Flat types skip the HNSW graph, so Sigmie does not send `m` and `ef_construction`.

Each index type gets its own vector field name, for example `bbq_hnsw_m64_efc300_dims768_cosine_concat`. A new type adds a new field, so reindex your documents after you change it. OpenSearch supports only `hnsw`. See [OpenSearch](opensearch.md).

### Several vectors on one field

Call `semantic()` or `newSemantic()` more than once on the same field. Each call adds one vector:

```php
$jobDescription = $props->text('job_description');

$jobDescription->semantic(api: 'embeddings', dimensions: 512, accuracy: 3);
$jobDescription->semantic(api: 'embeddings', dimensions: 512, accuracy: 5)
    ->euclideanSimilarity();
```

## Supply your own embeddings

To skip the embeddings API, for example in a backfill, put the vectors in the document under `_embeddings`. Key them by field path, then by vector field name:

```php
new Document([
    'title' => 'AI Research Paper',
    '_embeddings' => [
        'title' => [
            'm24_efc150_dims256_cosine_avg' => [0.1, 0.2, 0.3, /* ... */],
        ],
    ],
]);
```

Sigmie skips a field only when the document has a vector for every vector field of that field. It embeds the other fields as usual. To list the vector field names of a field:

```php
$props->get()->get('title')->vectorFields()
    ->map(fn ($vector) => $vector->name)
    ->toArray();
// ['m24_efc150_dims256_cosine_avg']
```

## Rerank the top results

A cross-encoder reranker scores each hit against the query and gives a better order for the top results. Register a rerank API, fetch more hits than you need, then call `rerank()` on the response:

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

$top5 = $response->rerank('my-rerank', ['content'], topK: 5); // [tl! highlight]
```

[Retrieval and Agents](rag.md) covers rerankers and passing results to a language model.

To find documents similar to documents you already have, use [Recommendations](recommendations.md).

## Troubleshoot

**No results.** Fetch one document with `$sigmie->collect('articles')->get($id)` and check that it has an `_embeddings` key. If it does not, check that the `api` name on the field matches the name you registered.

**Slow searches.** Lower the accuracy or the dimensions. Add filters so that fewer documents reach vector ranking.

**High memory use.** Lower the accuracy or the dimensions, or use a quantized index type.
