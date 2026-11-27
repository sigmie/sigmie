---
title: Introduction
short_description: What Sigmie is and when to use it. A PHP library that turns Elasticsearch and OpenSearch query DSL into field types, filter strings, and one search chain.
keywords: [introduction, overview, sigmie, elasticsearch, opensearch, php]
category: Getting Started
order: 1
related_pages: [installation, quick-start, core-concepts]
---

# Introduction

Sigmie is a PHP library for Elasticsearch and OpenSearch. You describe what a field holds and what the search should do. Sigmie writes the mappings, analyzers, and query DSL for you.

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('lapto')
    ->typoTolerance()
    ->typoTolerantAttributes(['name'])
    ->filters('price<=1500 AND in_stock:true')
    ->facets('brand price:100')
    ->highlighting(['name', 'description'], prefix: '<mark>', suffix: '</mark>')
    ->sort('_score:desc price:asc')
    ->get();
```

Written by hand, the same request is a nested array of `bool`, `match`, `range`, `highlight`, and aggregation clauses. Sigmie builds that array from the chain and the field types.

## Why Sigmie exists

Elasticsearch is flexible, and that flexibility costs time. A good product search needs the right analyzer on each field, a keyword sub-field for filters, a sortable sub-field for sorting, fuzzy matching on some fields but not others, and aggregations that ignore the filter of their own facet. Each decision lives in a different part of a large JSON body.

Sigmie moves those decisions into three places that PHP code can read:

| Place | You write | Sigmie generates |
|-------|-----------|------------------|
| Field types | `$props->title('name')` | Mapping, analyzer, sub-fields, and the queries for that field |
| Filter and sort strings | `'price:100..500 AND in_stock:true'` | `bool` filter clauses and `sort` entries |
| The search chain | `->typoTolerance()->facets('brand')` | Query clauses, fuzziness, and aggregations |

The trade-off is control. Sigmie picks the analyzer and query shape for each field type. When the defaults do not fit, `newQuery()` builds a query clause by clause, and `rawQuery()` sends a query array as is. See [Advanced queries](query.md).

## Field types carry the search behavior

Elasticsearch knows `text`, `keyword`, and number types. Sigmie adds types that say what the data is for:

```php
$props = new NewProperties;
$props->title('name');           // word-boundary tokens, phrase-prefix matching, sortable
$props->category('brand');       // full text plus exact filters and facets
$props->price();                 // scaled_float with range filters and histogram facets
$props->text('bio')->keyword();  // full text plus a keyword sub-field for exact filters
```

The type decides the mapping and the queries. A `title()` field runs a phrase-prefix query, so `lapto` already matches "Laptop Pro". A `price()` field returns min, max, and a histogram when you facet on it. See [Mappings and properties](mappings.md) for every type.

## Filter strings replace boolean queries

A filter is a string that the [filter parser](filter-parser.md) turns into `bool` filter clauses:

```php
->filters('category:"electronics" AND price:100..500 AND in_stock:true')
```

The parser checks each field against the properties you pass. A filter on a missing or non-filterable field adds a message to the response's `errors` list instead of failing silently. Pass `throwOnError: true` to get a `ParseException` instead. Sort strings work the same way through the [sort parser](sort-parser.md).

## Semantic search uses the same chain

A text field becomes semantic when you call `semantic()` on it and register an embeddings API under the name it uses:

```php
$props->text('description')->semantic(api: 'embeddings', dimensions: 384);

$sigmie->registerApi('embeddings', $embeddingsApi);

$results = $sigmie->newSearch('products')
    ->properties($props)
    ->semantic()
    ->queryString('portable work computer')   // matches "laptop" and "notebook"
    ->get();
```

Sigmie embeds the field when it indexes a document, and embeds the query string when it searches. Keyword matching, filters, and facets still apply. See [Semantic search](semantic-search.md).

## When to use Sigmie

Sigmie fits when search is a feature of your PHP application: a product catalog, a content library, a support knowledge base, or retrieval for an AI agent. It also fits Laravel projects through the [Laravel Scout](laravel-scout.md) driver and the [Laravel AI SDK](laravel-ai.md) tools.

Use the official Elasticsearch client instead when you need a feature Sigmie does not expose, or when a large codebase already builds raw queries. The two can share a cluster. Inside Sigmie, `rawQuery()` sends a query array unchanged.

## Next steps

- [Installation](installation.md) installs the package and connects to a cluster.
- [Quick start](quick-start.md) builds a product search from an empty index.
- [Core concepts](core-concepts.md) explains the client, indices, documents, and properties.
