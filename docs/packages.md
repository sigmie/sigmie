---
title: Packages
short_description: The modular Sigmie packages
keywords: [packages, composer, modules, standalone packages]
category: Reference
order: 2
related_pages: [installation, extending]
---

# Packages

`sigmie/sigmie` is a meta-package that pulls in everything you need for typical use. If you want a leaner install — for example, you only need the filter parser, or you're building a tool that uses just the HTTP client — you can require individual packages directly.

## Standard installation

```bash
composer require sigmie/sigmie
```

This installs everything below as transitive dependencies. Most applications start (and stay) here.

## Individual packages

| Package | Purpose |
|---------|---------|
| `sigmie/base` | Driver abstractions for Elasticsearch and OpenSearch. |
| `sigmie/http` | HTTP client built on Guzzle, with auth and multi-host support. |
| `sigmie/index` | Index builders, analyzers, and language modules. |
| `sigmie/document` | The `Document` and `AliveCollection` classes. |
| `sigmie/mappings` | Property types and the `NewProperties` builder. |
| `sigmie/query` | The low-level query builder (`NewQuery`). |
| `sigmie/search` | The high-level search builder (`NewSearch`). |
| `sigmie/parse` | Filter and sort string parsers. |
| `sigmie/testing` | Test utilities and assertions. |
| `sigmie/english` | English language analyzers and filters. |
| `sigmie/german` | German language analyzers and filters. |
| `sigmie/greek` | Greek language analyzers and filters. |

```bash
composer require sigmie/parse           # filter/sort parsing only
composer require sigmie/mappings        # build property mappings
composer require sigmie/search          # high-level search
composer require sigmie/testing         # test helpers
```

## Integration packages

Separately maintained:

| Package | Purpose |
|---------|---------|
| `sigmie/elasticsearch-scout` | Laravel Scout driver. |

```bash
composer require sigmie/elasticsearch-scout
```

See [Laravel Scout](laravel-scout.md).

## Extension packages

For shipping field types and document-processing hooks, see [Extending Sigmie](extending.md). Each external package registers itself on a `Sigmie` instance via `$sigmie->extend()`.
