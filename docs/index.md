---
title: Indices
short_description: Create, configure, and update Elasticsearch indices with Sigmie — schema, analysis, sharding, replication, zero-downtime updates, and deletion.
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

## Rebuild an index

`update()` copies documents that are already in Elasticsearch. When the source of truth lives somewhere else, such as a database or a CSV feed, use `rebuild()`. It builds a fresh index from your data, then swaps it in with no downtime:

```php
use Sigmie\Document\AliveCollection;

$sigmie->newIndex('movies')
    ->properties($props)
    ->rebuild(function (AliveCollection $docs) use ($movies) {
        $docs->merge($movies);
    });
```

`rebuild()`:

1. Creates a new physical index with the index's settings and mappings.
2. Runs your callback to fill it. Searches keep using the current index.
3. Points the `movies` alias at the new index in one atomic request.
4. Deletes the previous index.

The first `rebuild()` of an alias creates it, so the same call works for the first build and for every refresh after it.

```
movies (alias) ──► movies_20260101   (live, searchable)
                   movies_20260201   ◄── callback fills it

movies (alias) ──► movies_20260201   (one _aliases request)
                   movies_20260101   deleted
```

If the callback throws, Sigmie deletes the new index, keeps the alias where it was, and rethrows.

An empty result usually means a failed import, so `rebuild()` refuses it. It throws `Sigmie\Index\RebuildTooSmall` and keeps the live index. Raise or lower the bar with `minDocuments`:

```php
$sigmie->newIndex('movies')->rebuild($fill, minDocuments: 1000);
$sigmie->newIndex('movies')->rebuild($fill, minDocuments: 0);   // allow empty
```

> **Note:** The callback's collection has no properties, the same as `$sigmie->collect()`. To validate documents or populate semantic fields while filling, call `$docs->properties($props)` first.

> **Note:** `rebuild()` does not lock. If two processes can rebuild the same alias at once, serialise them yourself. Otherwise the slower rebuild fails at the swap and leaves its new index behind.

### Rebuild across processes

When one process can't write every document in time, split the rebuild into start, fill, and finish. Any process can resume the pending rebuild by its alias, so queued jobs need nothing but the name:

```php
// Once: create the new index. The alias stays on the live index.
$sigmie->newIndex('movies')->properties($props)->startRebuild();

// In each job: write one chunk.
$sigmie->rebuilding('movies')->collect()->merge($chunk);

// Once all jobs succeed: swap the alias and delete the old index.
$sigmie->rebuilding('movies')->finish(minDocuments: 1);

// If a job fails: drop the new index. Searches never saw it.
$sigmie->rebuilding('movies')->abort();
```

`startRebuild()` turns refresh off and replicas to zero for fast bulk writes. `finish()` restores them before the swap. `count()` reports the documents written so far.

`rebuilding()` returns the newest unattached index that `startRebuild()` created for the alias, or `null`. The state lives in the index's `_meta`, so it survives restarts and queue retries. `rebuild()` itself is `startRebuild()`, your callback, then `finish()`.

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
