---
title: Indices
short_description: Create, configure, update, rebuild, inspect, and delete Elasticsearch indices with Sigmie, with zero-downtime alias swaps for schema and analysis changes.
keywords: [index, indices, create index, update index, rebuild index, shards, replicas, aliases]
category: Core Concepts
order: 2
related_pages: [document, mappings, core-concepts]
---

# Indices

These guides cover the life of an index, from creation to deletion. Each index name you pass is an alias over a physical index. [Core Concepts](core-concepts.md#an-index-name-is-an-alias) explains why.

## Create an index

Pass your [properties](mappings.md) to `newIndex()`, then call `create()`:

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->title('title');
$props->name('director');
$props->category('genre');
$props->number('year')->integer();
$props->date('release_date');

$sigmie->newIndex('movies')
    ->properties($props) // [tl! highlight]
    ->create();
```

`create()` returns the new index. It throws `Sigmie\Index\Alias\AliasAlreadyExists` when the name is taken. To get the existing index instead, call `createIfNotExists()`.

Without `properties()`, Elasticsearch guesses each field type from the first document it receives.

To write documents into the new index, see [Documents](document.md).

## Set the default analyzer

The default analyzer processes every text field that has no analyzer of its own. Chain tokenizer and filter calls before `create()`:

```php
$sigmie->newIndex('movies')
    ->properties($props)
    ->tokenizeOnWhitespaces()
    ->lowercase()
    ->trim()
    ->create();
```

For the available calls, see [Tokenizers](tokenizers.md), [Token Filters](token-filters.md), and [Char Filters](char-filters.md).

### Use a language analyzer

Call `language()` first. It returns a language builder with extra filters, such as the English stemmer:

```php
use Sigmie\Languages\English\English;

$sigmie->newIndex('articles')
    ->language(new English) // [tl! highlight]
    ->properties($props)
    ->englishStemmer()
    ->englishStopwords()
    ->englishLowercase()
    ->create();
```

> **Warning:** `language()` starts a new builder. It keeps the shards and replicas, but drops properties, filters, and `config()` values set before it. Call those after `language()`.

[Languages](language.md) lists the English, German, and Greek builders.

## Set shards and replicas

```php
$sigmie->newIndex('movies')
    ->shards(3)
    ->replicas(1)
    ->create();
```

Without these calls, Sigmie creates 1 shard and 2 replicas. On Elasticsearch Serverless, Sigmie sends neither setting, because the service manages them. [Core Concepts](core-concepts.md#shards-split-an-index-replicas-copy-it) explains shards and replicas.

## Set any other index setting

Pass any [Elasticsearch index module setting](https://www.elastic.co/guide/en/elasticsearch/reference/current/index-modules.html) to `config()`:

```php
$sigmie->newIndex('movies')
    ->config('index.max_ngram_diff', 3)
    ->create();
```

## Update an index

To change the properties or the analysis of an index that holds documents, call `update()`. The callback receives an `UpdateIndex` builder with the same methods as `newIndex()`:

```php
use Sigmie\Index\UpdateIndex;

$sigmie->index('movies')->update(function (UpdateIndex $update) {
    $update->properties($newProperties);
    $update->lowercase();

    return $update;
});
```

`update()` copies the documents that are already in Elasticsearch:

1. Creates a new physical index from the callback's settings, such as `movies_20260201120000000000`.
2. Blocks writes to the current index and reindexes every document into the new one.
3. Points the `movies` alias at the new index.
4. Deletes the old index.

Searches keep working through every step. Writes to `movies` fail from step 2 until step 4 ends.

> **Warning:** `update()` does not merge settings. It builds the new index only from what the callback declares. Declare again every property, filter, shard count, and replica count that you want to keep.

## Rebuild an index

When the source of truth lives outside Elasticsearch, such as a database or a CSV feed, call `rebuild()`. It fills a new index from your data, then swaps it in:

```php
use Sigmie\Document\AliveCollection;

$sigmie->newIndex('movies')
    ->properties($props)
    ->rebuild(function (AliveCollection $docs) use ($movies) {
        $docs->merge($movies);
    });
```

`rebuild()` does four things:

1. Creates a new physical index with the builder's settings and mappings.
2. Runs your callback to fill it. Searches keep using the current index.
3. Points the `movies` alias at the new index in one `_aliases` request.
4. Deletes the previous index.

```
movies (alias) ──► movies_20260101   (live, searchable)
                   movies_20260201   ◄── callback fills it

movies (alias) ──► movies_20260201   (one _aliases request)
                   movies_20260101   deleted
```

If no alias exists yet, `rebuild()` creates it. The same call works for the first build and for every refresh after it.

If the callback throws, Sigmie deletes the new index, leaves the alias where it was, and throws the exception again.

`rebuild()` also refuses a result with fewer documents than `minDocuments`, which defaults to 1. An empty result usually means a failed import. Sigmie deletes the new index, keeps the live one, and throws `Sigmie\Index\RebuildTooSmall`. Change the limit with `minDocuments`:

```php
$sigmie->newIndex('movies')->rebuild($fill, minDocuments: 1000);
$sigmie->newIndex('movies')->rebuild($fill, minDocuments: 0);   // allow empty
```

> **Note:** The callback's collection has no properties, the same as `$sigmie->collect()`. To check documents or generate embeddings for semantic fields while you fill it, call `$docs->properties($props)` first.

> **Note:** `rebuild()` does not lock. If two processes can rebuild the same alias at once, run them one after the other yourself. Otherwise the slower rebuild fails at the swap and leaves its new index behind.

### Rebuild across processes

When one process cannot write every document in time, split the rebuild into start, fill, and finish. Any process can resume the pending rebuild by its alias, so a queued job needs only the name:

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

`startRebuild()` turns refresh off and sets replicas to zero for fast bulk writes. `finish()` restores both before the swap. `count()` returns the number of documents written so far.

`rebuilding()` returns the newest index that `startRebuild()` created for the alias and that no alias points to yet. It returns `null` when no rebuild is pending. Sigmie stores the rebuild state in the index's `_meta`, so it survives restarts and queue retries. `rebuild()` is `startRebuild()`, your callback, then `finish()`.

## Inspect an index

`$sigmie->index()` returns the index, or `null` when it does not exist:

```php
$index = $sigmie->index('movies');

$index->name;                            // physical name, e.g. movies_20260101120000000000
$index->mappings;                        // index mappings
$index->mappings->properties();          // property definitions
$index->settings;                        // index settings
$index->raw;                             // raw Elasticsearch response
$index->analyze('The Matrix');           // tokens from the default analyzer
```

## Delete an index

```php
$sigmie->index('movies')->delete();
```

To delete without loading the index first, call `$sigmie->delete()` or `$sigmie->deleteIfExists()`. Both take an alias or a physical name, or several names separated by commas:

```php
$sigmie->deleteIfExists('movies,series');
```
