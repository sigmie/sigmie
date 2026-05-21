---
title: Indices
short_description: Create, configure, update, and delete indices
keywords: [index, indices, create index, shards, replicas, aliases]
category: Core Concepts
order: 2
related_pages: [document, mappings, core-concepts]
---

# Indices

An index is a container for related documents — closer to a database table than a folder. Unlike a relational database, you don't have to define columns before inserting rows: Elasticsearch will create the index and infer field types on first write. But for serious applications you almost always define a schema first.

## Create an index

The simplest possible index:

```php
$sigmie->newIndex('movies')->create();
```

That works, but Elasticsearch will guess at field types as you index documents. For control over how fields are stored and searched, pass [properties](mappings.md):

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->title('title');
$props->name('director');
$props->category('genre');
$props->number('year')->integer();
$props->date('release_date');

$sigmie->newIndex('movies')
    ->properties($props)
    ->create();
```

## Configure analysis

Index-level analysis controls how text is tokenized and normalized at index time:

```php
$sigmie->newIndex('movies')
    ->properties($props)
    ->tokenizeOnWhitespaces()       // split on whitespace
    ->lowercase()                   // normalize to lowercase
    ->trim()                        // strip surrounding whitespace
    ->create();
```

The same analyzer runs on query strings at search time, so a query for `Matrix` matches a document storing `matrix`.

See [Analysis](analysis.md) and [Token Filters](token-filters.md) for the full pipeline.

### Language analyzers

```php
use Sigmie\Languages\English\English;

$sigmie->newIndex('articles')
    ->properties($props)
    ->language(new English)
    ->englishStemmer()
    ->englishStopwords()
    ->englishLowercase()
    ->create();
```

See [Languages](language.md) for English, German, and Greek builders.

### Autocomplete

```php
$props = new NewProperties;
$props->title('title');
$props->text('description');
$props->autocomplete();

$sigmie->newIndex('movies')
    ->properties($props)
    ->autocomplete(['title', 'description'])
    ->create();
```

## Sharding and replication

```php
$sigmie->newIndex('movies')
    ->shards(3)
    ->replicas(1)
    ->create();
```

A shard is a smaller index that holds a subset of documents. An index with 3 shards and 8 documents distributes like:

```
movies
├─ shard 1
│  ├─ document 1
│  ├─ document 2
│  └─ document 3
├─ shard 2
│  ├─ document 4
│  ├─ document 5
│  └─ document 6
└─ shard 3
   ├─ document 7
   └─ document 8
```

A replica is a copy of a shard on another node, for fault tolerance. With 3 primaries and 2 replicas across 3 nodes:

```
cluster
├─ node 1
│  ├─ primary 1
│  ├─ replica of primary 2
│  └─ replica of primary 3
├─ node 2
│  ├─ primary 2
│  ├─ replica of primary 1
│  └─ replica of primary 3
└─ node 3
   ├─ primary 3
   ├─ replica of primary 1
   └─ replica of primary 2
```

If a node fails, the surviving nodes still hold every document. Replicas are promoted to primaries automatically.

For most workloads, keep each shard under 30 GB.

## Add documents

To write documents into an index, get a **collection** for it:

```php
use Sigmie\Document\Document;

$sigmie->collect('movies')
    ->merge([
        new Document(['title' => 'Cinderella']),
        new Document(['title' => 'Snow White']),
        new Document(['title' => 'Sleeping Beauty']),
    ]);
```

To make documents immediately searchable (for tests), pass `refresh: true`:

```php
$sigmie->collect('movies', refresh: true)->merge($documents);
```

See [Documents](document.md) for the full collection API.

## Update an index

Elasticsearch indices are immutable: once analysis is applied to a document, you can't re-analyze it without re-indexing. Sigmie provides an `update()` method that handles this transparently using **aliases**:

```php
use Sigmie\Index\UpdateIndex;

$sigmie->index('movies')->update(function (UpdateIndex $update) {
    $update->properties($newProperties);
    $update->lowercase();
});
```

Behind the scenes, `update()`:

1. Creates a new physical index with a timestamp suffix.
2. Reindexes every document into the new index.
3. Switches the `movies` alias to point at the new index.
4. Deletes the old index.

```
Step 1: Create new index
movies (alias) ──► movies_20221122210823379774
                   ├─ Cinderella
                   ├─ Snow White
                   └─ Sleeping Beauty

movies_20221222210823379774   (empty)

Step 2: Reindex
movies (alias) ──► movies_20221122210823379774
                   ├─ Cinderella
                   ├─ Snow White
                   └─ Sleeping Beauty

movies_20221222210823379774
├─ Cinderella
├─ Snow White
└─ Sleeping Beauty

Step 3: Swap alias
movies_20221122210823379774   (orphaned)

movies (alias) ──► movies_20221222210823379774
                   ├─ Cinderella
                   ├─ Snow White
                   └─ Sleeping Beauty

Step 4: Delete old index
movies (alias) ──► movies_20221222210823379774
```

To clients, the index name is unchanged. There's no downtime.

> **Warning:** Index settings are **not** merged. Anything you don't re-declare in the `update()` callback is dropped. Re-set everything you want to keep.

## Inspect an index

```php
$index = $sigmie->index('movies');

$index->mappings;                        // index mappings
$index->mappings->properties();          // property definitions
$index->raw;                             // raw Elasticsearch response
$index->analyze('The Matrix');           // run text through the analyzer
```

## Delete an index

```php
$sigmie->index('movies')->delete();
```

## Advanced settings

Apply any [Elasticsearch index module setting](https://www.elastic.co/guide/en/elasticsearch/reference/current/index-modules.html) with `config()`:

```php
$sigmie->newIndex('movies')
    ->config('index.max_ngram_diff', 3)
    ->create();
```
