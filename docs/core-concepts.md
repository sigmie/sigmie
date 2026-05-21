---
title: Core Concepts
short_description: The four pieces of Sigmie that everything else composes from — the client, indices, documents, and properties — and how they fit together.
keywords: [core concepts, fundamentals, client, indices, documents, properties]
category: Core Concepts
order: 1
related_pages: [index, document, mappings, search]
---

# Core Concepts

Sigmie has four moving parts:

1. **The client** (`Sigmie`) — your connection.
2. **Indices** — containers for documents, with mappings and settings.
3. **Documents** — the JSON records you store and search.
4. **Properties** — the schema that tells Elasticsearch how to treat each field.

Everything in Sigmie composes from these four pieces.

## The client

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);
```

The client is the entry point to every other operation:

```php
$sigmie->newIndex('movies');                // build a new index
$sigmie->index('movies');                   // load an existing one
$sigmie->collect('movies');                 // get a writable collection
$sigmie->newSearch('movies');               // build a search
$sigmie->newQuery('movies');                // build a raw query
$sigmie->newRecommend('movies');            // build a recommendation
```

See [Installation](installation.md) and [Connection Setup](connection.md) for connection options.

## Indices

An index is a container for related documents — closer to a database table than a file. Sigmie builds an index with a schema and a set of analysis settings:

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->title('title');
$props->name('director');
$props->number('year')->integer();

$sigmie->newIndex('movies')
    ->properties($props)
    ->create();
```

Index settings — sharding, replication, custom analyzers — are configured before `create()`:

```php
$sigmie->newIndex('movies')
    ->properties($props)
    ->shards(3)
    ->replicas(1)
    ->lowercase()
    ->tokenizeOnWhitespaces()
    ->create();
```

See [Indices](index.md) for the full lifecycle: creation, update, deletion.

## Documents

Documents are JSON objects you store in an index:

```php
use Sigmie\Document\Document;

$movie = new Document([
    'title' => 'The Matrix',
    'director' => 'The Wachowskis',
    'year' => 1999,
]);
```

Documents are written through a **collection**:

```php
$sigmie->collect('movies', refresh: true)
    ->merge([
        new Document(['title' => 'The Matrix', 'year' => 1999]),
        new Document(['title' => 'Inception', 'year' => 2010]),
    ]);
```

`refresh: true` makes documents immediately searchable. Use it in tests; skip it in production.

See [Documents](document.md) for adding, iterating, and bulk operations.

## Properties

Properties define the schema. They control:

- **Type** — how values are interpreted (`text`, `number`, `bool`).
- **Analysis** — how text is broken into tokens.
- **Sub-fields** — for example, a `text` field with a `.keyword` sub-field for sorting.
- **Queries** — which query types match each field.

```php
$props = new NewProperties;
$props->title('title');             // optimized for searchable titles
$props->name('director');           // optimized for personal/place names
$props->category('genre');          // exact-match filterable
$props->price('ticket_price');      // numeric, supports ranges
$props->date('release_date');
$props->text('description')->keyword();   // full-text + sortable/filterable
```

The high-level types are wrappers over Elasticsearch's `text`, `keyword`, `number`, etc. They wire up the right analyzers and queries so you don't have to.

See [Mappings & Properties](mappings.md) for every type.

## How the pieces fit

Use the same `NewProperties` instance for indexing and searching:

```php
$props = new NewProperties;
$props->title('title');
$props->name('director');

// At index creation
$sigmie->newIndex('movies')->properties($props)->create();

// At search time
$sigmie->newSearch('movies')->properties($props)->queryString('matrix')->get();
```

This is how Sigmie knows which queries to generate for each field. Skip it and your search falls back to a basic match query.

## Analysis: how text becomes searchable

Elasticsearch transforms text at index time so it can search it fast at query time. The same transformation runs on your query string at search time.

```
Document text                             Query string
   "The Matrix"                              "Matrix"
        │                                       │
        ▼                                       ▼
   [Char filters]                          [Char filters]
        │                                       │
        ▼                                       ▼
   [Tokenizer]                             [Tokenizer]
   ["The", "Matrix"]                       ["Matrix"]
        │                                       │
        ▼                                       ▼
   [Token filters]                         [Token filters]
   ["matrix"]    (lowercase, stopwords)    ["matrix"]
        │                                       │
        └──────────► term match ◄───────────────┘
```

The text "The Matrix" is stored as the single token `matrix`. The query "Matrix" gets lowercased to `matrix` too — so it matches.

Sigmie exposes index analysis through the index builder:

```php
$sigmie->newIndex('movies')
    ->tokenizeOnWhitespaces()
    ->lowercase()
    ->trim()
    ->create();
```

You can verify what an analyzer produces:

```php
$tokens = $sigmie->index('movies')->analyze('The Matrix');   // ["matrix"]
```

See [Analysis](analysis.md) for the full pipeline, and [Tokenizers](tokenizers.md) / [Token Filters](token-filters.md) for the building blocks.

## Search vs. query

Two ways to retrieve documents:

```php
// High-level: user-facing search, with typo tolerance, highlighting, facets
$sigmie->newSearch('movies')
    ->properties($props)
    ->queryString('matrix sci-fi')
    ->typoTolerance()
    ->highlighting(['title'])
    ->facets('genre')
    ->get();

// Low-level: raw Elasticsearch boolean queries
$sigmie->newQuery('movies')
    ->properties($props)
    ->bool(function ($bool) {
        $bool->must()->match('title', 'matrix');
        $bool->filter()->range('year', ['>' => 1990]);
        $bool->should()->term('genre', 'sci-fi');
    })
    ->get();
```

Reach for [`newSearch()`](search.md) for user-facing search. Reach for [`newQuery()`](query.md) when you need full control over the Elasticsearch DSL.

## What's next

- Building your first feature: [Quick Start](quick-start.md).
- Designing your schema: [Mappings & Properties](mappings.md).
- Managing data: [Indices](index.md) and [Documents](document.md).
- Searching: [Search](search.md) and [Query Builder](query.md).
