---
title: Magic Tags
short_description: The Magic Tags package adds LLM-generated tags to Elasticsearch documents, reuses existing tags through embeddings, and shares a tag registry across indices.
keywords: [magic tags, taxonomy, llm, embeddings, sidecar, classification]
category: Features
order: 2
related_pages: [semantic-search, mappings, document, aggregations, extending]
---

# Magic Tags

Magic Tags adds a `keyword` field whose values an LLM writes: short labels that describe another field's content. The package prefers existing tags over new ones, so the tag list stays stable for search, filters, and agent tools.

> **Note:** Magic Tags is a separate [Sigmie package](extending.md), not part of this repository. This page describes its intended behavior. The examples assume that you installed and registered the package.

## How the package stores tags

The package writes tags to two indices:

```
Main index documents                       Sidecar index (tag registry)
+---------------------------+              +-----------------------------+
| content (semantic text)   |              | magic_field_path (keyword)  |
| topic (magic_tags)        |    sync      | tag (short text + vectors)  |
| _embeddings.content ...   |  ─────────►  | _embeddings.tag ...         |
+---------------------------+              +-----------------------------+
        │                                            │
        │                                            │
   LLM + optional                          Same embedding API as
   classify-first                          the source field
```

The **main index** stores each document's tags as an array of strings. The mapping is `keyword` with `meta.type` set to `magic_tags`.

The **sidecar index** holds one row for each unique `(magic_field_path, tag)` pair, with an embedding of the tag text. Its name defaults to `{logicalName}__sigmie_magic_tags`. Each row has the `_id` `md5(path::tag)`, so a repeated write updates the row and does not add a duplicate. The sidecar uses the same embeddings API and dimensions as the source field.

The package runs as a `CollectionHook` during `merge()` and `add()`. `beforeBatch()` creates the sidecar index if it does not exist. `processBatch()` selects the tags for each document. `afterBatch()` upserts the new tags into the sidecar. For the hook lifecycle, see [Extending Sigmie](extending.md).

## Install the package

Register the package on your `Sigmie` client:

```php
use Sigmie\Sigmie;
use Vendor\MagicTags\MagicTagsPackage;

$sigmie = new Sigmie($connection);
$sigmie->extend(new MagicTagsPackage());
```

`extend()` adds the `magicTags()` macro to `NewProperties` for the whole PHP process. It adds the collection hook to this `Sigmie` instance only.

## Add a magic tags field

The source field must be a [semantic](semantic-search.md) text field, because the package reads its embeddings configuration to set up the sidecar.

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;

$props->text('content')
    ->semantic(api: 'my-embeddings', accuracy: 1, dimensions: 1024);

$props->magicTags('topic', fromField: 'content')
    ->api('my-llm');
```

Register both API names on the collection:

```php
$collection = $sigmie->collect('kb', refresh: true)
    ->properties($props)
    ->apis([
        'my-llm' => $llmApi,
        'my-embeddings' => $embeddingsApi,
    ]);
```

Index a document with `merge()` or `add()`:

```php
use Sigmie\Document\Document;

$collection->merge([
    new Document(['content' => 'How to reset a circuit breaker.']),
]);
```

The stored document now has a `topic` array of tags.

## Tune classification and deduplication

For each document, the package selects tags in this order:

1. **Classify.** This step runs only when `classifyFirst(true)` is set and the sidecar has at least `minTagsForClassification` tags. The package embeds the source text and compares it with a centroid for each tag, built from sample passages. Tags with a similarity of at least `classifyConfidence` apply without an LLM call.
2. **Generate.** If classification returns no tags, the LLM writes tags from the source text. The prompt lists existing tags so that the LLM can reuse them.
3. **Deduplicate.** The package compares each new tag with existing tags by embedding similarity. A tag above `similarityThreshold` counts as a duplicate.

Set these values on the field:

```php
$props->magicTags('topic', fromField: 'content')
    ->api('my-llm')
    ->embeddingsApi('my-embeddings')
    ->embeddingDimensions(1024)
    ->classifyFirst(true)
    ->minTagsForClassification(10)        // need 10+ tags before classifying
    ->classifyConfidence(0.3)             // minimum centroid similarity
    ->classifySamplesPerTag(5)            // passages per tag for centroid
    ->similarityThreshold(0.85)           // dedup threshold
    ->maxTags(5);
```

To always call the LLM, turn classification off:

```php
$props->magicTags('topic', fromField: 'content')
    ->api('my-llm')
    ->classifyFirst(false);
```

## Replace the LLM prompt

`prompt()` replaces the default LLM instructions:

```php
$props->magicTags('topic', fromField: 'content')
    ->api('my-llm')
    ->prompt(
        'You tag property-management support content. Return up to 5 lowercase '.
        'kebab-case tags. Prefer reusing tags from the existing list when applicable.'
    );
```

## Share one tag registry across indices

To use one sidecar for several indices, give each field the same `tagIndex()` name. The main index names stay different.

```php
$shared = 'property_app';

$kb = new NewProperties;
$kb->text('content')->semantic(api: 'my-embeddings', accuracy: 1, dimensions: 1024);
$kb->magicTags('topic', fromField: 'content')
    ->api('my-llm')
    ->tagIndex($shared);

$memory = new NewProperties;
$memory->text('content')->semantic(api: 'my-embeddings', accuracy: 1, dimensions: 1024);
$memory->magicTags('topic', fromField: 'content')
    ->api('my-llm')
    ->tagIndex($shared);
```

Both collections write to `property_app__sigmie_magic_tags`. The `magic_field_path` of each row shows which source field produced the tag.

> **Note:** The LLM prompt lists existing tags from the **current** main index only. To show the LLM tags from all collections, merge the tag lists yourself before you call `merge()`.

## Index documents that already have tags

To skip the pipeline for a batch whose documents already have their final tags, call `withoutHooks()`:

```php
$collection->withoutHooks()->merge($documents);
```

## List the tags for a filter or agent

To show the available tags in a filter menu or an agent tool, run a `terms` aggregation on the magic tags field:

```php
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Search as QuerySearch;

$aggs = new Aggs;
$aggs->terms('by_topic', 'topic')->size(20);

$response = (new QuerySearch($connection))
    ->index('kb')
    ->query(new MatchAll)
    ->aggs($aggs)
    ->size(0)
    ->get();

$buckets = $response->json('aggregations.by_topic.buckets');
// [['key' => 'returns', 'doc_count' => 42], ['key' => 'shipping', 'doc_count' => 18], ...]
```

This list is separate from the tag list that the package sends to the LLM, which is larger. For other bucket options, see [Aggregations](aggregations.md).

## See also

- [Extending Sigmie](extending.md): packages, macros, and collection hooks.
- [Semantic Search](semantic-search.md): semantic fields and embeddings.
- [Mappings & Properties](mappings.md): property builders.
- [Documents](document.md): collections, `add()`, and `merge()`.
