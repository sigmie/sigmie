---
title: OpenSearch
short_description: Run Sigmie on OpenSearch or AWS OpenSearch Service by changing one engine parameter, and see how semantic fields map to OpenSearch knn_vector settings.
keywords: [opensearch, aws, search engine, vector search, knn, semantic search]
category: Configuration
order: 3
related_pages: [connection, semantic-search, index]
---

# OpenSearch

Sigmie runs on OpenSearch with the same API it uses for Elasticsearch. You set the engine type when you connect. Index, document, and search code stays the same. Sigmie's test suite runs against OpenSearch 3.2.0.

## Connect to OpenSearch

Pass `SearchEngineType::OpenSearch` as the `engine`:

```php
use Sigmie\Sigmie;
use Sigmie\Enums\SearchEngineType;

$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch, // [tl! highlight]
    config: [
        'auth' => ['admin', 'MyStrongPass123!@#'],
        'verify' => false,    // self-signed certificate in development
    ]
);
```

For TLS, API keys, and other connection options, see [Connection Setup](connection.md).

## Move from Elasticsearch

Add the `engine` argument to `Sigmie::create()`:

```php
use Sigmie\Enums\SearchEngineType;

// Before
$sigmie = Sigmie::create(hosts: ['https://localhost:9200']);

// After
$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch,
);
```

The rest of your code does not change. Indices do not move between engines. Build each index again on the new cluster, then index your documents into it.

## Connect to AWS OpenSearch Service

For a domain with username and password access:

```php
$sigmie = Sigmie::create(
    hosts: ['https://your-domain.region.es.amazonaws.com'],
    engine: SearchEngineType::OpenSearch,
    config: ['auth' => ['username', 'password']],
);
```

For a domain that requires IAM-signed requests, see the AWS section of [Connection Setup](connection.md).

## Semantic fields on OpenSearch

Define semantic fields the same way you do for Elasticsearch:

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->text('title')->semantic(api: 'embeddings', dimensions: 384);
$props->text('content')->semantic(api: 'embeddings', accuracy: 3, dimensions: 384);

$sigmie->newIndex('articles')->properties($props)->create();
```

Search them the same way too:

```php
$results = $sigmie->newSearch('articles')
    ->properties($props)
    ->semantic()
    ->queryString('quantum computing')
    ->size(20)
    ->get();
```

For accuracy levels, similarity, and providers, see [Semantic Search](semantic-search.md).

## What Sigmie changes on OpenSearch

The engine type changes how Sigmie writes the mapping and the settings:

| Setting | Elasticsearch | OpenSearch |
|---------|---------------|------------|
| Vector field type | `dense_vector` | `knn_vector`, `lucene` engine, `hnsw` method |
| Index setting | none | `index.knn: true` |
| Vector index types | `hnsw`, `int8_hnsw`, `int4_hnsw`, `bbq_hnsw`, `flat`, `int8_flat`, `int4_flat`, `bbq_flat` | `hnsw` only |

Sigmie maps each `VectorSimilarity` case to the OpenSearch space type:

| `VectorSimilarity` | OpenSearch `space_type` |
|--------------------|-------------------------|
| `Cosine` (default) | `cosinesimil` |
| `DotProduct` | `innerproduct` |
| `MaxInnerProduct` | `innerproduct` |
| `Euclidean` | `l2` |

> **Warning:** A semantic field with an `indexType()` other than `hnsw` throws an exception on OpenSearch when Sigmie builds the mapping.
