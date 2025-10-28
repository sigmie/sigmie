---
title: OpenSearch
short_description: Use OpenSearch as your search engine with Sigmie
keywords: [opensearch, aws, search engine, vector search, knn, semantic search]
category: Configuration
order: 3
related_pages: [connection, semantic-search, index]
---

# OpenSearch

Sigmie supports OpenSearch, AWS's open-source search engine. This guide shows how to use OpenSearch instead of the default Elasticsearch.

## Basic Usage

To use OpenSearch, specify the engine type when creating your connection:

```php
use Sigmie\Sigmie;
use Sigmie\Enums\SearchEngineType;

$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch,
    config: [
        'auth' => ['admin', 'password'],
        'verify' => false  // For self-signed certificates
    ]
);
```

That's it! All Sigmie APIs now work with OpenSearch. The driver automatically handles the differences between OpenSearch and Elasticsearch.

## Supported Versions

Sigmie supports OpenSearch 2.x and 3.x:
- OpenSearch 2.4.x, 2.5.x, 2.11.x
- OpenSearch 3.0.x
- AWS OpenSearch Service

Both versions work identically - Sigmie automatically adapts to your OpenSearch version.

## Creating Indices with Semantic Search

Create indices with semantic search fields the same way you would with Elasticsearch:

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->text('title')->semantic(dimensions: 384);
$props->text('content')->semantic(dimensions: 384, accuracy: 3);

$sigmie->newIndex('articles')
    ->properties($props)
    ->create();
```

Sigmie automatically configures OpenSearch's KNN settings for you.

## Accuracy Levels

Control search quality with the `accuracy` parameter:

```php
$props->text('content')->semantic(
    dimensions: 384,
    accuracy: 3  // 1 (fast) to 5 (precise), default: 3
);
```

| Accuracy | Use Case |
|----------|----------|
| 1 | Fast indexing, many documents |
| 2 | Balanced performance |
| 3 | Recommended (default) |
| 4-5 | High precision |

## Similarity Metrics

Choose how vectors are compared:

```php
use Sigmie\Enums\VectorSimilarity;

// Cosine similarity (default)
$props->text('description')->semantic(
    dimensions: 384,
    similarity: VectorSimilarity::Cosine
);

// Dot product
$props->text('abstract')->semantic(
    dimensions: 384,
    similarity: VectorSimilarity::DotProduct
);

// Euclidean distance
$props->text('content')->semantic(
    dimensions: 384,
    similarity: VectorSimilarity::Euclidean
);
```

## Performing Searches

Search with OpenSearch works exactly like Elasticsearch:

```php
// Semantic search
$search = $sigmie->newSearch('articles')
    ->semantic()
    ->queryString('quantum computing')
    ->size(20);

$results = $search->get();

// Regular text search
$search = $sigmie->newSearch('articles')
    ->queryString('quantum')
    ->size(10);

$results = $search->get();
```

## Switching from Elasticsearch

To migrate from Elasticsearch to OpenSearch, simply change the engine parameter:

```php
// Before (Elasticsearch)
$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200']
);

// After (OpenSearch)
$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch
);
```

All your existing Sigmie code continues to work without changes.

## AWS OpenSearch Service

To connect to AWS OpenSearch Service:

```php
$sigmie = Sigmie::create(
    hosts: ['https://your-domain.region.es.amazonaws.com'],
    engine: SearchEngineType::OpenSearch,
    config: [
        'auth' => ['username', 'password']
    ]
);
```

## Related Documentation

- **[Connection Setup](/docs/connection)** - Authentication and SSL configuration
- **[Semantic Search](/docs/semantic-search)** - Vector search guide
- **[Creating Indices](/docs/index)** - Index management
