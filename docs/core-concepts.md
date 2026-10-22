---
title: Core Concepts
short_description: How the Sigmie client, indices, documents, and properties fit together, and why one schema drives the Elasticsearch mapping, validation, and queries.
keywords: [core concepts, fundamentals, client, indices, documents, properties, aliases, shards]
category: Core Concepts
order: 1
related_pages: [index, document, mappings, search]
---

# Core Concepts

Sigmie has four parts. The client holds the connection. An index stores documents. A document is one JSON record. Properties describe each field, and Sigmie reads them in three places:

```
                     ┌──► newIndex()->properties()    mapping and analyzers
NewProperties $props ┼──► collect()->properties()     validation and embeddings
                     └──► newSearch()->properties()   the queries for each field
```

The rest of this page explains each part and why Sigmie wires them this way. For a first working search, follow the [Quick Start](quick-start.md).

## The client is the entry point

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);
```

Every other builder starts from the client:

```php
$sigmie->newIndex('movies');                // build a new index
$sigmie->index('movies');                   // load an existing one
$sigmie->collect('movies');                 // read and write documents
$sigmie->newSearch('movies');               // build a user-facing search
$sigmie->newQuery('movies');                // build a raw query
$sigmie->newRecommend('movies');            // build a recommendation
```

[Installation](installation.md) and [Connection Setup](connection.md) cover hosts, credentials, and OpenSearch.

## An index name is an alias

An index holds related documents, like a table in a database. When you call `$sigmie->newIndex('movies')->create()`, Sigmie creates a physical index with a timestamp suffix and points the alias `movies` at it:

```
movies (alias) ──► movies_20260101120000000000 (physical index)
```

Your code only uses `movies`. The alias exists because Elasticsearch cannot change the analysis or the field types of an index that already holds documents. To change them, Sigmie builds a new physical index, fills it, and then moves the alias to it. Searches keep using the old index until the move. [Update an index](index.md#update-an-index) and [Rebuild an index](index.md#rebuild-an-index) use this.

### Shards split an index, replicas copy it

A shard is a smaller index that holds part of the documents. An index with 3 shards and 8 documents spreads them, for example, like this:

```
movies
├─ shard 1: document 1, document 2, document 3
├─ shard 2: document 4, document 5, document 6
└─ shard 3: document 7, document 8
```

A replica is a copy of a shard on another node. With 3 primary shards and 2 replicas on 3 nodes, every node holds one primary and a copy of the other two:

```
cluster
├─ node 1: primary 1, replica of 2, replica of 3
├─ node 2: primary 2, replica of 1, replica of 3
└─ node 3: primary 3, replica of 1, replica of 2
```

If a node fails, the other nodes still hold every document, and Elasticsearch promotes a replica to primary. Sigmie creates 1 shard and 2 replicas unless you call `shards()` and `replicas()`. For most workloads, keep each shard under 30 GB.

## Documents go through a collection

A `Document` is a PHP wrapper around one JSON object and its optional `_id`. You write and read documents through a collection, which `$sigmie->collect('movies')` returns:

```php
use Sigmie\Document\Document;

$sigmie->collect('movies')->merge([
    new Document(['title' => 'The Matrix', 'year' => 1999]),
    new Document(['title' => 'Inception', 'year' => 2010]),
]);
```

The collection does not hold the documents in memory. Each call goes to Elasticsearch, so `count()` and `each()` always reflect the index. Elasticsearch makes a write searchable after the next refresh, about one second later by default. [Documents](document.md) covers writing, reading, and refresh control.

## Properties do three jobs

`NewProperties` describes your fields once. Sigmie reads that description at three points:

| Where you pass `$props` | What Sigmie does with it |
|-------------------------|--------------------------|
| `newIndex()->properties()` | Writes the Elasticsearch mapping and the analyzer for each field. |
| `collect()->properties()` | Checks each document before the write, formats `DateTime` values, and generates embeddings for semantic fields. |
| `newSearch()->properties()` | Picks the fields to search and the queries to run on each one. |

Pass the same instance at all three points. A search without properties knows no fields, so a query string returns no hits.

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->title('title');
$props->name('director');

$sigmie->newIndex('movies')->properties($props)->create();

$sigmie->newSearch('movies')->properties($props)->queryString('matrix')->get();
```

### High-level types carry search behavior

Elasticsearch knows storage types such as `text`, `keyword`, and `integer`. A storage type says nothing about how users search the field. Sigmie adds high-level types that bundle the storage type with an analyzer, sub-fields, and queries. For example, `$props->title('title')` produces:

```
title()  ──► mapping   text field, word tokens, lowercase
         ──► sub-fields title.keyword, title.sortable
         ──► queries   match_phrase_prefix + match
```

A `name()` field uses n-grams instead, so "Wacho" finds "Wachowski". Choose the type that matches how users type into the search box. [Mappings & Properties](mappings.md) lists every type with its mapping and its queries.

## Analysis turns text into tokens

Elasticsearch does not search the stored text. It searches tokens that an analyzer produced at index time. At search time, the same analyzer runs on the query string, so both sides compare equal tokens:

```
Document text: "The Matrix"            Query string: "Matrix"
        │                                      │
   tokenizer ──► ["The", "Matrix"]        tokenizer ──► ["Matrix"]
        │                                      │
   lowercase ──► ["the", "matrix"]        lowercase ──► ["matrix"]
        │                                      │
        └──────────── "matrix" = "matrix" ─────┘   match
```

You set the default analyzer on the index builder, and each high-level type can bring its own:

```php
$sigmie->newIndex('movies')
    ->tokenizeOnWhitespaces()
    ->lowercase()
    ->create();

$sigmie->index('movies')->analyze('The Matrix');   // ["the", "matrix"]
```

[Text Analysis](analysis.md) explains the pipeline. [Tokenizers](tokenizers.md) and [Token Filters](token-filters.md) list the parts.

## Search and query serve different users

Sigmie has two retrieval builders. `newSearch()` takes what a user typed and builds the Elasticsearch request from your properties. It adds typo tolerance, highlighting, and facets on request. `newQuery()` takes the boolean query you write, clause by clause, and sends it as written.

```php
$sigmie->newSearch('movies')
    ->properties($props)
    ->queryString('matrix sci-fi')
    ->typoTolerance()
    ->highlighting(['title'], '<em>', '</em>')
    ->facets('genre')
    ->get();

$sigmie->newQuery('movies')
    ->properties($props)
    ->bool(function ($bool) {
        $bool->must()->match('title', 'matrix');
        $bool->filter()->range('year', ['>' => 1990]);
        $bool->should()->term('genre', 'sci-fi');
    })
    ->get();
```

Use [`newSearch()`](search.md) for a search box. Use [`newQuery()`](query.md) when your code, not a user, decides the exact clauses.
