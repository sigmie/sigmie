---
title: Introduction
short_description: Learn what Sigmie is and when to use it for Elasticsearch search
keywords: [introduction, overview, sigmie, elasticsearch, search library, php]
category: Getting Started
order: 1
related_pages: [installation, quick-start, core-concepts]
---

# Introduction

## What is Sigmie?

Sigmie is a Laravel-inspired PHP library that makes Elasticsearch feel natural. Here's a search with typo tolerance, filtering, and highlighting:

```php
$results = $sigmie->newSearch(name: 'products')
    ->properties($props)
    ->queryString('laptop')
    ->weight(['title' => 3, 'description' => 1])
    ->typoTolerance() // [tl! highlight]
    ->filters('price<=1500 AND in_stock:true') // [tl! highlight]
    ->sort('_score:desc', 'price:asc')
    ->highlighting(['title', 'description'])
    ->get();
```

A fluent API that reads like the feature you're building. No nested arrays, no obscure DSL rules—just expressive, chainable methods.

## When to Use Sigmie

**Use Sigmie if you want to:**
- Build search features without becoming an Elasticsearch expert
- Write readable, maintainable search code
- Add semantic search with vector embeddings
- Implement faceted filtering for e-commerce or content discovery
- Get typo tolerance, highlighting, and filtering without configuration overhead

**Consider the raw Elasticsearch client if:**
- You need every possible Elasticsearch feature (Sigmie covers 90% of common use cases)
- You're migrating a large codebase already built on raw queries
- You need ultra-low-level control over every query parameter

## Core Features at a Glance

**Field Types That Know Their Purpose**
```php
$props = new NewProperties;
$props->title('name');        // Optimized for searchable titles // [tl! focus]
$props->category('brand');    // For exact-match filtering // [tl! focus]
$props->price();              // Min/max ranges and histograms // [tl! focus]
$props->text('bio')->keyword(); // Full-text search + filtering
```

**Human-Readable Filters**
```php
->filters('category:"electronics" AND price:100..500 AND in_stock:true') // [tl! focus]
```

**Semantic Search with Vector Embeddings**
```php
$props->text('description')->semantic( // [tl! focus:start]
    accuracy: 3,
    dimensions: 384,
    api: 'embeddings'
); // [tl! focus:end]

$results = $sigmie->newSearch(name: 'products')
    ->properties($props)
    ->semantic() // [tl! highlight]
    ->queryString('portable work computer') // Finds "laptop", "notebook", etc.
    ->get();
```

**Faceted Search for Filtering Interfaces**
```php
$response = $sigmie->newSearch(name: 'products')
    ->queryString('laptop')
    ->facets('brand category price:50') // [tl! highlight]
    ->get();

$brands = $response->json('facets.brand'); // [tl! focus]
// ['Apple' => 12, 'Dell' => 8, 'HP' => 5]
```

## Real-World Example

Let's build a product search with typo tolerance, faceted filtering, and semantic matching:

```php
use Sigmie\Mappings\NewProperties;
use Sigmie\Document\Document;

// 1. Define your schema
$props = new NewProperties;
$props->title('name');
$props->text('description')->semantic(accuracy: 3, dimensions: 384, api: 'embeddings'); // [tl! highlight]
$props->category('brand')->facetDisjunctive(); // [tl! highlight]
$props->category('category')->facetDisjunctive(); // [tl! highlight]
$props->price();
$props->bool('in_stock');

// 2. Create the index
$sigmie->newIndex('products')
    ->properties($props)
    ->create();

// 3. Add documents
$sigmie->collect('products', refresh: true)
    ->properties($props)
    ->merge([
        new Document([
            'name' => 'MacBook Pro 16"',
            'description' => 'High-performance laptop for professionals',
            'brand' => 'Apple',
            'category' => 'Laptops',
            'price' => 2499,
            'in_stock' => true,
        ]),
        new Document([
            'name' => 'ThinkPad X1 Carbon',
            'description' => 'Lightweight business notebook with long battery life',
            'brand' => 'Lenovo',
            'category' => 'Laptops',
            'price' => 1599,
            'in_stock' => true,
        ]),
    ]);

// 4. Search with everything enabled
$results = $sigmie->newSearch(name: 'products')
    ->properties($props)
    ->queryString('profesional latop') // Typos are OK // [tl! focus]
    ->semantic()                        // Semantic + keyword matching // [tl! highlight]
    ->typoTolerance()                   // Fix spelling mistakes // [tl! highlight]
    ->filters('in_stock:true')          // Only available products // [tl! highlight]
    ->facets('brand category price:100') // Sidebar filters // [tl! highlight]
    ->weight(['name' => 3, 'description' => 1]) // Title matters more
    ->sort('_score:desc', 'price:asc')  // Best match, then cheapest
    ->get();

// 5. Use the results
foreach ($results->hits() as $hit) { // [tl! focus:start]
    echo $hit['name'] . ' - $' . $hit['price'] . "\n";
} // [tl! focus:end]

$facets = $results->json('facets'); // [tl! focus]
// ['brand' => ['Apple' => 1, 'Lenovo' => 1], 'price' => ['min' => 1599, 'max' => 2499, ...]]
```

This example demonstrates:
- Semantic search finding "professional laptop" despite typos and synonyms
- Facets for building filter interfaces
- Weighted fields for relevance tuning
- Readable filter syntax
- Multi-level sorting

## Key Capabilities

**Semantic Search & Vector Embeddings**
Use OpenAI, Cohere, or custom embeddings to search by meaning, not just keywords. Sigmie handles vector generation, storage, and similarity scoring.

**Faceted Search**
Automatic aggregations for term counts, price ranges, and statistics. Supports disjunctive (OR) and conjunctive (AND) filtering.

**Typo Tolerance**
Built-in fuzzy matching handles spelling mistakes without configuration.

**Multi-Language Support**
Pre-configured analyzers for German, English, and other languages with proper stemming and normalization.

**Laravel Integration**
Drop-in Laravel Scout driver for seamless Eloquent model integration.

## What's Next?

Ready to dive in? Here's your path:

**For beginners:**
Start with [Quick Start](/docs/quick-start.md) to build your first search in 5 minutes.

**For experienced developers:**
Explore [Semantic Search](/docs/semantic-search.md) for AI-powered features.

**For Laravel users:**
Jump to [Laravel Scout Integration](/docs/laravel-scout.md) for Eloquent integration.

**For production systems:**
Review [Index Management](/docs/index.md) and [Performance Optimization](/docs/performance.md).

## Requirements

- PHP 8.1 or higher
- Elasticsearch 9.x or OpenSearch 3.x
- Composer for installation

## Getting Help

- **Documentation**: Comprehensive guides with examples at [sigmie.com](https://sigmie.com)
- **GitHub Issues**: [github.com/sigmie/sigmie](https://github.com/sigmie/sigmie)
- **Security**: Report vulnerabilities to nico@sigmie.com

Elasticsearch is powerful. Sigmie makes it accessible.
