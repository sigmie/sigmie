---
title: Extending Sigmie
short_description: Build packages with field types and document processing hooks
keywords: [extending, package, plugin, hooks, field types, collection]
category: Advanced Features
order: 90
related_pages: [mappings, document, search, magic-tags]
---

# Extending Sigmie

Sigmie has a single registration point for external packages. A package can add custom field-type builder methods to `NewProperties` and document-processing hooks that fire during `merge()` and `add()`.

The [Magic Tags](magic-tags.md) package is a real-world example: it adds a `magicTags()` builder and a `CollectionHook` that calls an LLM and writes to a sidecar index. Core Sigmie knows nothing about either — only the package does.

## Bootstrap a package

```php
use Sigmie\Sigmie;
use Vendor\MagicTags\MagicTagsPackage;

$sigmie = new Sigmie($connection);
$sigmie->extend(new MagicTagsPackage());
```

`extend()` calls the package's `register()` immediately and binds the hook to **this** `Sigmie` instance — not process-wide static state. Two clients in the same PHP process can have different extensions registered.

> **Note:** `NewProperties` macros are process-global. In tests that need isolation, call `NewProperties::flushMacros()` between cases.

## The `Package` interface

A package implements `Sigmie\Contracts\Package`:

```php
namespace Vendor\MagicTags;

use Sigmie\Contracts\Package;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;

class MagicTagsPackage implements Package
{
    public function register(Sigmie $sigmie): void
    {
        NewProperties::macro('magicTags', /* ... */);
        $sigmie->addCollectionHook(new MagicTagsCollectionHook());
    }
}
```

`register()` runs once per `extend()` call.

## Field-type macros

`NewProperties::macro()` adds a method that behaves like a built-in field type:

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

After registration, callers use it like any native type:

```php
$props = new NewProperties;
$props->text('content')->semantic(api: 'embeddings', accuracy: 1, dimensions: 1024);
$props->magicTags('topic', fromField: 'content')
    ->api('llm')
    ->embeddingsApi('embeddings');
```

## Collection hooks

`CollectionHook` lets your package intervene around document indexing through `merge()` and `add()`. Register a hook on the same `Sigmie` instance:

```php
$sigmie->addCollectionHook(new MagicTagsCollectionHook());
```

A hook implements `Sigmie\Document\Contracts\CollectionHook`:

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
        // Ensure the sidecar index exists with the right mapping.
    }

    public function processBatch(
        array $documents,
        Properties $properties,
        array $apis
    ): array {
        // Classify, call the LLM, dedup tags. Return updated documents.
        return $documents;
    }

    public function afterBatch(
        array $documents,
        string $indexName,
        Sigmie $sigmie,
        Properties $properties,
        array $apis
    ): void {
        // Upsert (magic_field_path, tag) rows into the sidecar.
    }
}
```

### `shouldRun()`

Gate the hook on whether the collection's properties contain your field type. This keeps the hook from firing on unrelated indices — including any sidecar indices your package creates:

```php
public function shouldRun(Properties $properties): bool
{
    return $properties->fieldsOfType(MagicTags::class)->isNotEmpty();
}
```

### The `$apis` array

`processBatch()` and `afterBatch()` receive a map of registered API name → instance, populated from `Sigmie::registerApi()` and per-collection `apis()`.

Core Sigmie only registers `EmbeddingsApi` and `RerankApi` implementations. If your package needs an LLM client, inject it in the package constructor (or resolve it from your application container):

```php
$embeddings = $apis['my-embeddings'] ?? null;    // EmbeddingsApi
$rerank = $apis['my-rerank'] ?? null;            // RerankApi
$llm = $this->llmClient;                         // your own dependency
```

## Skip hooks on demand

`withoutHooks()` indexes documents without running any registered hooks — useful when documents already carry the values your hook would generate:

```php
$sigmie->collect('kb')->withoutHooks()->merge($documents);
```

## Full example

```php
namespace Vendor\MagicTags;

use Sigmie\Contracts\Package;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;
use Vendor\MagicTags\Types\MagicTags;

class MagicTagsPackage implements Package
{
    public function register(Sigmie $sigmie): void
    {
        NewProperties::macro('magicTags', function (string $name, string $fromField): MagicTags {
            $field = new MagicTags($name, $fromField);
            $this->add($name, $field);

            return $field;
        });

        $sigmie->addCollectionHook(new MagicTagsCollectionHook());
    }
}
```

Application bootstrap:

```php
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;
use Vendor\MagicTags\MagicTagsPackage;

$sigmie = new Sigmie($connection);
$sigmie->extend(new MagicTagsPackage());

$props = new NewProperties;
$props->text('content')->semantic(api: 'embeds', accuracy: 1, dimensions: 1024);
$props->magicTags('topic', fromField: 'content')->api('llm')->embeddingsApi('embeds');

$sigmie->collect('kb', refresh: true)
    ->properties($props)
    ->apis([
        'llm' => $llmApi,
        'embeds' => $embeddingsApi,
    ])
    ->merge([/* documents */]);
```

Every `merge()` / `add()` on a collection whose properties contain `MagicTags` fields runs the hook for that batch — for the same `Sigmie` instance you called `extend()` on.

## See also

- [Magic Tags](magic-tags.md) — a complete package built on this API.
- [Mappings & Properties](mappings.md) — field types and properties.
- [Documents](document.md) — collection lifecycle.
