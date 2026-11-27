---
title: Packages
short_description: Reference for the Sigmie Composer packages: what sigmie/sigmie contains, the HTTP, testing, and Laravel Scout packages, and which legacy packages to avoid.
keywords: [packages, composer, modules, sigmie/sigmie, sigmie/testing, sigmie/http]
category: Reference
order: 2
related_pages: [installation, extending]
---

# Packages

`sigmie/sigmie` is one package that contains the whole library. Install it with Composer:

```bash
composer require sigmie/sigmie
```

## Contents of `sigmie/sigmie`

The main namespaces in the package:

| Namespace | Contents |
|-----------|----------|
| `Sigmie\Base` | Connection, HTTP requests, and the Elasticsearch and OpenSearch drivers. |
| `Sigmie\Index` | `NewIndex`, analysis, aliases, updates, and rebuilds. |
| `Sigmie\Mappings` | `NewProperties` and the field types. |
| `Sigmie\Document` | `Document`, `AliveCollection`, and collection hooks. |
| `Sigmie\Query` | The low-level query builder, `NewQuery`, and aggregations. |
| `Sigmie\Search` | The search builder, `NewSearch`, and multi-search, templates, and recommendations. |
| `Sigmie\Parse` | The filter, sort, and facet string parsers. |
| `Sigmie\Languages` | English, German, and Greek analysis. |
| `Sigmie\AI` | Embedding, reranking, and CLIP API clients, and agent tools. |
| `Sigmie\Analytics` | Analytics queries. |
| `Sigmie\Contracts` | The `Package` interface for [extensions](extending.md). |

## Related packages

| Package | Namespace | Purpose |
|---------|-----------|---------|
| `sigmie/http` | `Sigmie\Http` | `JSONClient`, the Guzzle-based HTTP client. `sigmie/sigmie` requires it, so Composer installs it for you. |
| `sigmie/testing` | `Sigmie\Testing` | PHPUnit `TestCase`, `Assert`, and index assertions. The `sigmie/sigmie` archive leaves out its test helpers, so install this package to use them. |
| `sigmie/elasticsearch-scout` | `Sigmie\ElasticsearchScout` | Laravel Scout driver. See [Laravel Scout](laravel-scout.md). |

```bash
composer require --dev sigmie/testing
composer require sigmie/elasticsearch-scout
```

## Legacy packages

These packages are older splits of the library. `sigmie/sigmie` contains their code, so do not install them next to it.

| Package | Last version | Status |
|---------|--------------|--------|
| `sigmie/base` | 0.38.0 | Abandoned on Packagist. |
| `sigmie/shared` | 0.38.0 | Abandoned on Packagist. |
| `sigmie/mappings` | 0.38.0 | Abandoned on Packagist. |
| `sigmie/query` | 0.38.0 | Abandoned on Packagist. |
| `sigmie/search` | 0.38.0 | Abandoned on Packagist. |
| `sigmie/parse` | 0.38.0 | Abandoned on Packagist. |
| `sigmie/english` | 1.0.0 | Uses the old `Sigmie\English` namespace. Use `Sigmie\Languages\English` in `sigmie/sigmie`. |
| `sigmie/german` | 1.0.0 | Uses the old `Sigmie\German` namespace. Use `Sigmie\Languages\German` in `sigmie/sigmie`. |
| `sigmie/greek` | 1.0.0 | Uses the old `Sigmie\Greek` namespace. Use `Sigmie\Languages\Greek` in `sigmie/sigmie`. |
