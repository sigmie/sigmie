---
title: Magic Tags
short_description: LLM-generated taxonomy tags with embedding classification
keywords: [magic tags, taxonomy, llm, embeddings, sidecar, classification]
category: Features
order: 2
related_pages: [semantic-search, mappings, document, aggregations, extending]
---

# Magic Tags

Magic Tags adds a `keyword` field whose values come from an LLM: short, reusable labels (typically kebab-case) that describe another field's content. The pipeline favors **reusing** existing tags so your vocabulary stays stable for search, filtering, and downstream agent tooling.

> **Note:** Magic Tags is **not** part of this repository. It's a separate [Sigmie package](extending.md) that registers a `magicTags()` macro on `NewProperties` and a `CollectionHook` for indexing. This page documents the intended behavior. Examples assume the package is installed and registered.

Behind the scenes, the package maintains a **sidecar index** of unique tags with semantic embeddings on the tag text. The sidecar uses the same embeddings API and dimensions as your source field, so vector operations on tags stay consistent with the rest of your data.

## Install the package

```php
use Sigmie\Sigmie;
use Vendor\MagicTags\MagicTagsPackage;

$sigmie = new Sigmie($connection);
$sigmie->extend(new MagicTagsPackage());
```

`extend()` registers the macro and hook on **this Sigmie instance**. See [Extending Sigmie](extending.md) for the package interface.

## How it fits together

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

The **main index** stores tags as an array of strings on each document (mapped as `keyword` with `meta.type` `magic_tags`).

The **sidecar index** name defaults to `{logicalName}__sigmie_magic_tags`. Each row is one `(magic_field_path, tag)` pair with a deterministic `_id` (`md5(path::tag)`) so repeated writes upsert rather than duplicate.

## Define magic tags on a mapping

The source field must be a **semantic** text field — the package reads its embeddings configuration to set up the sidecar:

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;

$props->text('content')
    ->semantic(api: 'my-embeddings', accuracy: 1, dimensions: 1024);

$props->magicTags('topic', fromField: 'content')
    ->api('my-llm');
```

Register the same API names on the collection:

```php
$collection = $sigmie->collect('kb', refresh: true)
    ->properties($props)
    ->apis([
        'my-llm' => $llmApi,
        'my-embeddings' => $embeddingsApi,
    ]);
```

Now `merge()` and `add()` run the magic-tag pipeline:

```php
use Sigmie\Document\Document;

$collection->merge([
    new Document(['content' => 'How to reset a circuit breaker.']),
]);
```

The document gets a `topic` array populated by the LLM, with classification as a fast path when enough tags already exist.

## Generation order

When you index a document:

1. **Classify-first** (optional). If `classifyFirst(true)` and the sidecar has enough tags, the package embeds the source text and scores it against centroids built from sample passages per tag. Tags above the confidence threshold are applied without an LLM call.
2. **LLM fallback.** If classification returns nothing, the LLM generates tags from the source text plus a prompt listing existing tags for reuse.
3. **Dedup.** New tags are deduplicated against existing ones using embedding similarity.

## Configure classification and dedup

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

Disable classification entirely:

```php
$props->magicTags('topic', fromField: 'content')
    ->api('my-llm')
    ->classifyFirst(false);
```

## Custom prompt

Override the default LLM instructions:

```php
$props->magicTags('topic', fromField: 'content')
    ->api('my-llm')
    ->prompt(
        'You tag property-management support content. Return up to 5 lowercase '.
        'kebab-case tags. Prefer reusing tags from the existing list when applicable.'
    );
```

## Share one registry across indices

Point several mappings at the same `tagIndex()` logical name to share a single sidecar. Main index names stay different:

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

Both collections write to `property_app__sigmie_magic_tags`. Tag rows record `magic_field_path` so you can tell which source field produced each tag.

> **Note:** The "existing tags" list shown to the LLM during generation is fetched from the **current** main index only. If you want a global vocabulary across collections for the prompt, merge tag lists yourself before calling `merge()`.

## Skip the pipeline for a batch

When documents already carry final tag values:

```php
$collection->withoutHooks()->merge($documents);
```

## Use tags in an agent tool

A chatbot or filter UI typically wants a list of available tags. Run a terms aggregation on the magic-tag field:

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

This is separate from the internal tag list used during generation (which uses a larger `size`, often 500, so the LLM sees a broad vocabulary).

See [Aggregations](aggregations.md).

## What the package contains

A Magic Tags package typically registers:

- **`NewProperties::macro('magicTags', ...)`** so mappings can call `magicTags()`.
- **A `CollectionHook`** via `$sigmie->addCollectionHook(...)` implementing:
  - `shouldRun()` — checks `Properties::fieldsOfType(MagicTags::class)` so unrelated collections skip the hook.
  - `beforeBatch()` — ensures the sidecar index exists.
  - `processBatch()` — LLM + classification + dedup.
  - `afterBatch()` — upserts tag rows into the sidecar.

See [Extending Sigmie](extending.md) for the `Package` interface and the hook lifecycle.

## See also

- [Extending Sigmie](extending.md) — packages, macros, and collection hooks.
- [Semantic Search](semantic-search.md) — semantic fields and embeddings.
- [Mappings & Properties](mappings.md) — property builders.
- [Documents](document.md) — collections, `add`, `merge`.
- [Aggregations](aggregations.md) — terms buckets for tool selection.
