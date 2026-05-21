---
title: Documentation
short_description: Complete documentation for Sigmie — the PHP library that gives Elasticsearch and OpenSearch a fluent, Laravel-inspired search and indexing API.
keywords: [documentation, sigmie, elasticsearch, php]
category: Getting Started
order: 0
related_pages: [introduction, installation, quick-start]
---

# Sigmie Documentation

Sigmie is a PHP library that gives Elasticsearch a fluent, Laravel-inspired API. Define an index in a few lines, search with typo tolerance and faceting, add semantic search when you need it.

```php
use Sigmie\Sigmie;
use Sigmie\Mappings\NewProperties;

$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);

$props = new NewProperties;
$props->title('name');
$props->category('brand');
$props->price();

$sigmie->newIndex('products')->properties($props)->create();

$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('lapto')
    ->typoTolerance()
    ->filters('price<=1500')
    ->get();
```

## Where to start

- **[Introduction](introduction.md)** — what Sigmie is and when to reach for it.
- **[Installation](installation.md)** — install and connect.
- **[Quick Start](quick-start.md)** — build your first search end to end.

## Configuration

- [Connection Setup](connection.md) — authentication, SSL, multi-node.
- [Docker](docker.md) — local Elasticsearch and AI services via `docker-compose`.
- [OpenSearch](opensearch.md) — using OpenSearch instead of Elasticsearch.

## Core concepts

- [Core Concepts](core-concepts.md) — client, index, document, properties.
- [Indices](index.md) — creation, settings, updates, deletion.
- [Documents](document.md) — collections, indexing, iteration.
- [Mappings & Properties](mappings.md) — field types and schema.

## Search

- [Search](search.md) — `newSearch()` with filters, facets, typo tolerance, highlighting.
- [Advanced Queries](query.md) — `newQuery()` for raw Elasticsearch DSL.
- [Filter Parser](filter-parser.md) — human-readable filter syntax.
- [Sort Parser](sort-parser.md) — sort expression syntax.
- [Facets](facets.md) — faceted navigation and filter UIs.
- [Aggregations](aggregations.md) — metrics and bucket aggregations.

## AI features

- [Semantic Search](semantic-search.md) — vector fields and embeddings.
- [Recommendations](recommendations.md) — "more like this" with RRF and MMR.
- [Retrieval and Agents](rag.md) — how Sigmie fits with generation.
- [Magic Tags](magic-tags.md) — LLM-generated taxonomy tags (package).
- [Extending Sigmie](extending.md) — write packages with macros and hooks.

## Text analysis

- [Analysis](analysis.md) — how text is processed at index and query time.
- [Tokenizers](tokenizers.md) — splitting text into tokens.
- [Token Filters](token-filters.md) — transforming tokens (stemming, synonyms, stopwords).
- [Character Filters](char-filters.md) — pre-tokenizer text processing.
- [Languages](language.md) — language-specific analyzers (English, German, Greek).

## Integrations

- [Laravel Scout](laravel-scout.md) — Scout driver for Eloquent models.
- [Laravel AI SDK](laravel-ai.md) — expose indices as AI agent tools.
- [MCP Server](mcp.md) — connect AI agents to Sigmie docs.

## Reference

- [Packages](packages.md) — the modular Sigmie packages.

## Requirements

- PHP 8.1 or higher
- Elasticsearch 7.x, 8.x, or 9.x — or OpenSearch 2.x or 3.x
- Composer

## Installation

```bash
composer require sigmie/sigmie
```

## Support

- Issues: [github.com/sigmie/sigmie/issues](https://github.com/sigmie/sigmie/issues)
- Discussions: [github.com/sigmie/sigmie/discussions](https://github.com/sigmie/sigmie/discussions)
- Security: nico@sigmie.com

## License

Sigmie is open-source software released under the MIT license.
