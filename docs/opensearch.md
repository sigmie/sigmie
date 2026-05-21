---
title: OpenSearch
short_description: Use OpenSearch as your search engine
keywords: [opensearch, aws, search engine, vector search, knn, semantic search]
category: Configuration
order: 3
related_pages: [connection, semantic-search, index]
---

# OpenSearch

Sigmie supports OpenSearch 2.x and 3.x — including AWS OpenSearch Service — with the same API as Elasticsearch. You change one parameter and everything else continues to work.

## Connect

Specify the engine type:

```php
use Sigmie\Sigmie;
use Sigmie\Enums\SearchEngineType;

$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch,
    config: [
        'auth' => ['admin', 'MyStrongPass123!@#'],
        'verify' => false,    // self-signed cert in dev
    ]
);
```

## Supported versions

- OpenSearch 2.4 – 2.11
- OpenSearch 3.0+
- AWS OpenSearch Service

Sigmie adapts to the version automatically.

## Semantic search

Define semantic fields the same way as with Elasticsearch:

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->text('title')->semantic(dimensions: 384, api: 'embeddings');
$props->text('content')->semantic(dimensions: 384, accuracy: 3, api: 'embeddings');

$sigmie->newIndex('articles')->properties($props)->create();
```

Sigmie configures OpenSearch's KNN settings under the hood.

### Accuracy

| Level | Use case |
|-------|----------|
| 1 | Fastest indexing, large corpora |
| 2 | Balanced |
| 3 | Recommended default |
| 4–5 | Highest precision |

### Similarity metrics

```php
use Sigmie\Enums\VectorSimilarity;

$props->text('description')->semantic(
    dimensions: 384,
    similarity: VectorSimilarity::Cosine,        // default
);

$props->text('abstract')->semantic(
    dimensions: 384,
    similarity: VectorSimilarity::DotProduct,
);

$props->text('content')->semantic(
    dimensions: 384,
    similarity: VectorSimilarity::Euclidean,
);
```

## Searching

Search is identical to Elasticsearch:

```php
$results = $sigmie->newSearch('articles')
    ->properties($props)
    ->semantic()
    ->queryString('quantum computing')
    ->size(20)
    ->get();
```

## Migrate from Elasticsearch

Change one parameter:

```php
// Before
$sigmie = Sigmie::create(hosts: ['https://localhost:9200']);

// After
$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch,
);
```

The rest of your code is unchanged.

## AWS OpenSearch Service

For username/password auth:

```php
$sigmie = Sigmie::create(
    hosts: ['https://your-domain.region.es.amazonaws.com'],
    engine: SearchEngineType::OpenSearch,
    config: ['auth' => ['username', 'password']],
);
```

For IAM-signed requests, see the AWS section of [Connection Setup](connection.md).
