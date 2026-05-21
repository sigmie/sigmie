---
title: Introduction
short_description: What Sigmie is and when to use it
keywords: [introduction, overview, sigmie, elasticsearch, php]
category: Getting Started
order: 1
related_pages: [installation, quick-start, core-concepts]
---

# Introduction

Sigmie is a Laravel-inspired PHP library for Elasticsearch and OpenSearch. It replaces deeply nested query arrays with a fluent, chainable API that reads like the feature you're building.

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('lapto')
    ->typoTolerance()
    ->filters('price<=1500 AND in_stock:true')
    ->facets('brand category price:100')
    ->highlighting(['title', 'description'])
    ->sort('_score:desc price:asc')
    ->get();
```

That's a typo-tolerant, faceted, highlighted search with sorting and filtering — in one expression.

## When to use Sigmie

Reach for Sigmie when you want to:

- Build search features without becoming an Elasticsearch DSL expert.
- Get typo tolerance, highlighting, faceting, and filtering with sensible defaults.
- Add semantic search with vector embeddings.
- Stay close to your domain code instead of writing query JSON.
- Use Laravel Scout with a serious Elasticsearch backend.

Use the raw Elasticsearch client instead when you need every possible Elasticsearch feature, when you have a large existing codebase built on raw queries, or when you need direct control over query parameters Sigmie does not expose.

## High-level field types

Sigmie wraps Elasticsearch's `text`, `keyword`, `number`, and friends in **semantic field types** that tell Elasticsearch how to treat your data:

```php
$props = new NewProperties;
$props->title('name');         // short searchable titles
$props->category('brand');     // exact-match filterable
$props->price();               // numeric ranges and histograms
$props->text('bio')->keyword();// full-text search + sortable
```

Each high-level type configures the right analyzers, sub-fields, and queries underneath. See [Mappings & Properties](mappings.md).

## Human-readable filters

Skip the boolean query DSL. Write filters the way you'd describe them:

```php
->filters('category:"electronics" AND price:100..500 AND in_stock:true')
```

See [Filter Parser](filter-parser.md) for the full syntax.

## Semantic search

Add `->semantic()` to a text field and Sigmie generates vectors at index time using whatever embeddings API you registered:

```php
$props->text('description')->semantic(api: 'embeddings', dimensions: 384);

$sigmie->registerApi('embeddings', $embeddingsApi);

$results = $sigmie->newSearch('products')
    ->properties($props)
    ->semantic()
    ->queryString('portable work computer')   // finds "laptop", "notebook"
    ->get();
```

See [Semantic Search](semantic-search.md).

## Faceted search

Build sidebar filters for e-commerce or content discovery with a single method:

```php
$response = $sigmie->newSearch('products')
    ->queryString('laptop')
    ->facets('brand category price:100')   // [tl! highlight]
    ->get();

$facets = $response->json('facets');
// ['brand' => ['Apple' => 12, 'Dell' => 8], 'price' => ['min' => 599, ...]]
```

See [Facets](facets.md).

## End-to-end example

```php
use Sigmie\Sigmie;
use Sigmie\Mappings\NewProperties;
use Sigmie\Document\Document;

$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);

$props = new NewProperties;
$props->title('name');
$props->text('description');
$props->category('brand')->facetDisjunctive();
$props->price();
$props->bool('in_stock');

$sigmie->newIndex('products')->properties($props)->create();

$sigmie->collect('products', refresh: true)
    ->properties($props)
    ->merge([
        new Document([
            'name' => 'MacBook Pro 16"',
            'description' => 'High-performance laptop for professionals',
            'brand' => 'Apple',
            'price' => 2499,
            'in_stock' => true,
        ]),
        new Document([
            'name' => 'ThinkPad X1 Carbon',
            'description' => 'Lightweight business notebook',
            'brand' => 'Lenovo',
            'price' => 1599,
            'in_stock' => true,
        ]),
    ]);

$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('profesional latop')        // typos are fine
    ->typoTolerance()
    ->filters('in_stock:true')
    ->facets('brand price:100')
    ->weight(['name' => 3, 'description' => 1])
    ->sort('_score:desc price:asc')
    ->get();

foreach ($results->hits() as $hit) {
    echo $hit['name'] . ' - $' . $hit['price'] . "\n";
}
```

## Requirements

- PHP 8.1 or higher
- Elasticsearch 7.x, 8.x, or 9.x — or OpenSearch 2.x or 3.x
- Composer

## What's next

- New to the library: [Installation](installation.md), then [Quick Start](quick-start.md).
- Laravel user: [Laravel Scout](laravel-scout.md).
- Building AI agents: [Laravel AI SDK](laravel-ai.md) and [Retrieval and Agents](rag.md).
- Exploring the model: [Core Concepts](core-concepts.md).
