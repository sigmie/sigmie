---
title: Extending Sigmie
short_description: Build packages that add field types and document processing hooks to Sigmie
keywords: [extending, package, plugin, hooks, field types, collection]
category: Advanced Features
order: 90
related_pages: [mappings, document, search, magic-tags]
---

# Extending Sigmie

Sigmie ships with a single registration point for external packages. The **magic tags** feature is a good mental model: a package adds a `magicTags()` builder on mappings and a `CollectionHook` that fills tags and syncs a sidecar index. Nothing in core knows about magic tags — only your package does.

```php
use Vendor\MagicTags\MagicTagsPackage;
use Sigmie\Sigmie;

Sigmie::extend(new MagicTagsPackage());
```

One call registers everything the package needs — custom field-type builder methods and document processing hooks.

See [Magic Tags](magic-tags.md) for the product behavior (sidecar index, LLM, classification).

## The `Package` interface

Every Sigmie package implements `Sigmie\Contracts\Package`:

```php
namespace Vendor\MagicTags;

use Sigmie\Contracts\Package;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;

class MagicTagsPackage implements Package
{
    public function register(): void
    {
        NewProperties::macro('magicTags', /* ... */);
        Sigmie::addCollectionHook(new MagicTagsCollectionHook());
    }
}
```

`register()` is called immediately by `Sigmie::extend()`.

## Field-type macros

`NewProperties` supports macros — methods added at runtime that work identically to built-in ones like `keyword()`, `longText()`, etc.

A magic-tags package registers the `magicTags` macro so users map fields the same way as any other type:

```php
use Closure;
use Sigmie\Mappings\NewProperties;
use Vendor\MagicTags\Types\MagicTags;

NewProperties::macro('magicTags', function (string $name, string $fromField): MagicTags {
    $field = new MagicTags($name, $fromField);
    $this->add($name, $field);

    return $field;
});
```

After registration, users call it like a built-in type:

```php
$props = new NewProperties;

$props->text('content')->semantic(api: 'my-embeddings', accuracy: 1, dimensions: 1024);

$props->magicTags('topic', fromField: 'content')
    ->api('my-llm')
    ->embeddingsApi('my-embeddings');
```

## Collection hooks

A `CollectionHook` lets your package run logic before, during, and after documents are indexed via `AliveCollection::merge()` and `AliveCollection::add()`.

Register the hook inside the package's `register()`:

```php
use Sigmie\Sigmie;

Sigmie::addCollectionHook(new MagicTagsCollectionHook());
```

Implement `Sigmie\Document\Contracts\CollectionHook`. For magic tags, `shouldRun()` limits work to indices that actually declare `MagicTags` fields — so the hook does not run on unrelated collections or on the sidecar index itself.

```php
namespace Vendor\MagicTags;

use Sigmie\Document\Contracts\CollectionHook;
use Sigmie\Mappings\Properties;
use Sigmie\Sigmie;
use Vendor\MagicTags\Types\MagicTags;

class MagicTagsCollectionHook implements CollectionHook
{
    public function shouldRun(Properties $properties): bool
    {
        return $properties->fieldsOfType(MagicTags::class)->isNotEmpty();
    }

    public function beforeBatch(
        string $indexName,
        Sigmie $sigmie,
        Properties $properties,
        array $apis
    ): void {
        // Ensure {logicalName}__sigmie_magic_tags exists with the right mapping.
    }

    public function processBatch(
        array $documents,
        Properties $properties,
        array $apis
    ): array {
        // Centroid classification, LLM tag generation, dedup — return updated documents.
        return $documents;
    }

    public function afterBatch(
        array $documents,
        string $indexName,
        Sigmie $sigmie,
        Properties $properties,
        array $apis
    ): void {
        // Upsert (magic_field_path, tag) rows into the sidecar index.
    }
}
```

## `shouldRun()` and the `apis` array

`shouldRun(Properties $properties)` receives the index's field definitions. Use `Properties::fieldsOfType(MagicTags::class)` so the hook is skipped when there are no magic-tags fields — including sidecar indices your package creates.

The `$apis` array passed to `processBatch` / `afterBatch` is the map from `Sigmie::registerApi()`. **Core Sigmie only registers `EmbeddingsApi` and `RerankApi` implementations** (for semantic indexing and `$response->rerank(...)`). Magic tags need an LLM for tag text: inject that in your package (for example pass a client into `MagicTagsCollectionHook`’s constructor, or resolve it from your app container). Resolve embeddings the same way users registered them on the collection:

```php
$embeddings = $apis['my-embeddings'] ?? null;  // EmbeddingsApi
$rerank = $apis['my-rerank'] ?? null;            // RerankApi
```

## `withoutHooks()`

To index documents without triggering hooks — for example when documents already carry final tag values, or when the package writes to the sidecar without re-running generation:

```php
$sigmie->collect('kb')->withoutHooks()->merge($documents);
```

## Full example — `MagicTagsPackage`

The following ties macro + hook together. Your package fills in `MagicTags`, `MagicTagsCollectionHook`, index creation, LLM calls, and sidecar writes (see [Magic Tags](magic-tags.md)).

```php
namespace Vendor\MagicTags;

use Sigmie\Contracts\Package;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;
use Vendor\MagicTags\Types\MagicTags;

class MagicTagsPackage implements Package
{
    public function register(): void
    {
        NewProperties::macro('magicTags', function (string $name, string $fromField): MagicTags {
            $field = new MagicTags($name, $fromField);
            $this->add($name, $field);

            return $field;
        });

        Sigmie::addCollectionHook(new MagicTagsCollectionHook());
    }
}
```

Application bootstrap and mapping:

```php
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;
use Vendor\MagicTags\MagicTagsPackage;

Sigmie::extend(new MagicTagsPackage());

$props = new NewProperties;
$props->text('content')->semantic(api: 'embeds', accuracy: 1, dimensions: 1024);
$props->magicTags('topic', fromField: 'content')->api('llm')->embeddingsApi('embeds');

$sigmie->collect('kb', refresh: true)
    ->properties($props)
    ->apis(['llm' => $llmApi, 'embeds' => $embeddingsApi])
    ->merge([/* documents */]);
```

Any `merge()` or `add()` on a collection whose properties include `MagicTags` fields runs `MagicTagsCollectionHook` for that batch (unless you use `withoutHooks()`).
