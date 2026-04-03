---
title: Magic Tags
short_description: LLM-generated taxonomy tags with optional embedding classification and a shared tag sidecar index
keywords: [magic tags, taxonomy, llm, embeddings, sidecar, classification]
category: Features
order: 2
related_pages: [semantic-search, mappings, document, aggregations, extending]
---

# Magic Tags

> **Core vs package:** Magic tags are **not** implemented inside this repository. They are documented here so you know the intended behavior when you ship them as a [Sigmie package](extending.md) (macros + `CollectionHook` + sidecar index). The examples below assume a package registers `magicTags()` on `NewProperties` and wires the indexing pipeline.

## Introduction

Magic tags add a **keyword field** to your documents whose values come from an LLM: short, reusable labels (typically kebab-case) that describe the content of another field. The pipeline encourages **reuse** of existing tags so the vocabulary stays stable for search and for tools that filter a knowledge base before answering in chat.

A magic-tags package maintains a **sidecar index** of unique tags with **semantic embeddings** on the tag text. That index shares the same embedding API and dimensions as your semantic **source** field, so you can run vector operations on tags consistently with the rest of the stack. You optionally point several main indices at **one** logical tag registry so a chatbot or multi-index app uses a single vocabulary.

Bootstrap the package once (exact class names depend on your package):

```php
use Sigmie\Sigmie;

Sigmie::extend(new \Vendor\MagicTags\MagicTagsPackage());
```

## Core Concepts

```
Main index documents                    Sidecar index (tag registry)
+---------------------------+          +-----------------------------+
| content (semantic text)   |          | magic_field_path (keyword) |
| topic (magic_tags)        |  sync    | tag (short text + vectors) |
| _embeddings.content ...   |  ------> | _embeddings.tag ...         |
+---------------------------+          +-----------------------------+
        ^                                           ^
        |                                           |
   LLM + optional                          Same embedding API as
   classify-first                         source field (from mapping)
```

**Main index:** The magic tags field stores an array of strings on each document (mapped as `keyword` with `meta.type` `magic_tags`).

**Sidecar index:** Name is typically `{logicalName}__sigmie_magic_tags`. The logical name defaults to the main collection alias, or the value you pass to `tagIndex()` when you want several collections to share one registry. Each row is one `(magic_field_path, tag)` pair; document `_id` is deterministic (`md5(path::tag)`) so repeated writes **upsert** instead of duplicating rows.

**Generation order:** When classification is enabled and enough tags already exist, the package embeds the source text and scores it against **centroids** built from sample passages per tag (from aggregations on the main index). If classification returns nothing, or classification is off, the **LLM** fills tags. New tag strings get **deduplicated** against existing ones using embedding similarity when you configure an embeddings API on the `MagicTags` field.

## Defining Magic Tags on Your Index

Declare which field supplies the text to tag (`fromField`) and name the keyword field that stores the tags. The **source** field must be a **semantic** text field so the package can resolve embedding API name and dimensions for the sidecar.

```php
use Sigmie\Mappings\NewProperties;

$properties = new NewProperties;

$properties->text('content')
    ->semantic(api: 'my-embeddings', accuracy: 1, dimensions: 1024);

$properties->magicTags('topic', fromField: 'content')
    ->api('my-llm');
```

Register the same API names on the collection when you index:

```php
$collection = $sigmie->collect('kb', refresh: true)
    ->properties($properties)
    ->apis([
        'my-llm' => $llmApi,
        'my-embeddings' => $embeddingsApi,
    ]);
```

`merge()` and `add()` run the magic-tag pipeline when the package’s `CollectionHook` is active (see `shouldRun()` using `Properties::fieldsOfType(MagicTags::class)`).

```php
use Sigmie\Document\Document;

$collection->merge([
    new Document(['content' => 'How to reset a circuit breaker.']),
]);
```

> **Note:** If the source field is not semantic, the package should not create or write the sidecar index, because the sidecar mapping uses that field’s embedding configuration.

## Configuring Classification and Dedup

Classification and embedding-based dedup use the **`embeddingsApi`** on the `MagicTags` field (not only the source field’s API). Point it at the same logical embeddings API you use elsewhere:

```php
$properties->magicTags('topic', fromField: 'content')
    ->api('my-llm')
    ->embeddingsApi('my-embeddings')
    ->embeddingDimensions(1024);
```

Tune when centroid-based classification runs and how strict it is:

```php
$properties->magicTags('topic', fromField: 'content')
    ->api('my-llm')
    ->embeddingsApi('my-embeddings')
    ->classifyFirst(true)
    ->minTagsForClassification(10)
    ->classifyConfidence(0.3)
    ->classifySamplesPerTag(5)
    ->similarityThreshold(0.85)
    ->maxTags(5);
```

`classifyFirst(false)` skips the centroid step and relies on the LLM only.

## Customizing the LLM Prompt

Override the default system instructions for tag generation:

```php
$properties->magicTags('topic', fromField: 'content')
    ->api('my-llm')
    ->prompt(
        'You tag property support content. Return up to 5 lowercase kebab-case tags. '.
        'Prefer reuse of tags from the existing list when the user message includes them.'
    );
```

## Sharing One Tag Registry Across Indices

Use the same `tagIndex()` logical name on every mapping that should contribute to **one** sidecar. Main index names stay different; only the sidecar alias is shared.

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

The package creates `property_app__sigmie_magic_tags` once and upserts rows from both collections. Tag rows still record `magic_field_path` so you know which field path produced the tag.

> **Note:** Helpers that list existing tags and sample texts for the LLM typically read from the **current** main index only. Shared vocabulary for the LLM prompt across collections requires your application to merge tag lists from each index if you need a global list at generation time.

## Disabling Magic Tags for a Batch

Skip hooks when you ingest documents that already have tags:

```php
$collection->withoutHooks()->merge($documents);
```

## Surfacing Tags to an LLM Tool (Aggregations)

For a chatbot that picks filters before retrieval, run a **`terms`** aggregation on the **main** knowledge index on the magic tags field, with `size` set to how many buckets you want (for example **20**). That returns tag keys and **doc counts** for the LLM.

That query path is separate from the internal **existing-tags** fetch the package uses when **generating** tags (often a larger terms `size`, e.g. 500) so the LLM sees a broad list of existing labels to reuse. It does not limit your tool to 20 tags.

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

// Parse aggregations['by_topic']['buckets'] for keys and doc_count
```

See [Aggregations](aggregations.md) for the general aggregation API.

## How It Fits Together (package implementation)

A magic-tags package typically:

- Registers **`NewProperties::macro('magicTags', …)`** so mappings can call `magicTags('topic', fromField: 'content')`.
- Registers a **`CollectionHook`** via `Sigmie::addCollectionHook()` that implements `beforeBatch` (ensure sidecar index), `processBatch` (LLM + classification + dedup), and `afterBatch` (write tag rows to the sidecar).
- Uses **`Properties::fieldsOfType(MagicTags::class)`** inside `shouldRun()` so unrelated collections (including the sidecar index itself) do not run the hook.

See [Extending Sigmie](extending.md) for the `Package` interface, hooks, and `withoutHooks()`.

## See Also

- [Extending Sigmie](extending.md) — packages, macros, and collection hooks
- [Semantic Search](semantic-search.md) — semantic fields and embeddings
- [Mappings](mappings.md) — property builders and field types
- [Document](document.md) — collections, `add`, `merge`
- [Aggregations](aggregations.md) — terms buckets and counts for tools and dashboards
