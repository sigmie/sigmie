---
title: Extending Sigmie
short_description: Write a Sigmie package that adds custom field types to NewProperties with macros and runs CollectionHook code before and after documents are indexed.
keywords: [extending, package, plugin, hooks, field types, collection, macro]
category: Advanced Features
order: 90
related_pages: [mappings, document, search, magic-tags]
---

# Extending Sigmie

This page shows how to write a package that extends Sigmie. A package can add two things:

- Field-type methods on `NewProperties`, through macros.
- Collection hooks, which run code around `merge()` and `add()` when documents are indexed.

[Magic Tags](magic-tags.md) is a package built this way. It adds a `magicTags()` field type and a hook that calls an LLM and writes tags to a sidecar index. The examples on this page use it.

## Register a package

Call `extend()` on a `Sigmie` instance:

```php
use Sigmie\Sigmie;
use Vendor\MagicTags\MagicTagsPackage;

$sigmie = new Sigmie($connection);
$sigmie->extend(new MagicTagsPackage()); // [tl! highlight]
```

`extend()` calls the package's `register()` method at once. Each call to `extend()` calls `register()` again.

Hooks belong to the instance you call `extend()` on. Two `Sigmie` instances in one PHP process can have different hooks.

> **Note:** Macros are not per instance. `NewProperties::macro()` stores them in a static array for the whole process. In tests that need isolation, call `NewProperties::flushMacros()` between cases.

## Implement the `Package` interface

A package implements `Sigmie\Contracts\Package`. Register macros and hooks in `register()`:

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
            $this->add($field);

            return $field;
        });

        $sigmie->addCollectionHook(new MagicTagsCollectionHook());
    }
}
```

## Add a field type with a macro

`NewProperties::macro()` takes a name and a closure. Sigmie binds the closure to the `NewProperties` instance, so `$this` is the builder. Add the field with `$this->add()` and return it, so callers can chain methods on it:

```php
use Sigmie\Mappings\NewProperties;
use Vendor\MagicTags\Types\MagicTags;

NewProperties::macro('magicTags', function (string $name, string $fromField): MagicTags {
    $field = new MagicTags($name, $fromField);
    $this->add($field); // [tl! highlight]

    return $field;
});
```

After registration, callers use the macro like a built-in type:

```php
$props = new NewProperties;
$props->text('content')->semantic(api: 'embeddings', accuracy: 1, dimensions: 1024);
$props->magicTags('topic', fromField: 'content')
    ->api('llm')
    ->embeddingsApi('embeddings');
```

A call to a method with no macro throws `BadMethodCallException`.

## Add a collection hook

A hook implements `Sigmie\Document\Contracts\CollectionHook`. Register it on the same `Sigmie` instance:

```php
$sigmie->addCollectionHook(new MagicTagsCollectionHook());
```

The interface has four methods:

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
        // Create the sidecar index if it does not exist.
    }

    public function processBatch(
        array $documents,
        Properties $properties,
        array $apis
    ): array {
        // Call the LLM and add tags. Return every document.
        return $documents;
    }

    public function afterBatch(
        array $documents,
        string $indexName,
        Sigmie $sigmie,
        Properties $properties,
        array $apis
    ): void {
        // Write (magic_field_path, tag) rows to the sidecar index.
    }
}
```

### When each method runs

For `merge($documents)`, the collection calls the hooks in this order:

1. `shouldRun()` on each hook. Hooks that return `false` do not run for this batch.
2. `beforeBatch()` on each hook, once.
3. Sigmie formats dates, validates fields, and adds embeddings.
4. `processBatch()` on each hook, in the order you registered them. Each hook gets the documents the previous hook returned.
5. Sigmie writes the documents to the index.
6. `afterBatch()` on each hook, with the documents that were written.

`add($document)` runs the same steps for one document. `processBatch()` and `afterBatch()` get an array that holds only that document.

Hooks run on collections from `$sigmie->collect()`, and on the collections that `rebuild()` and `rebuilding()` give you. See [Indices](index.md) for rebuilds.

### Run only on matching indices

`shouldRun()` gets the collection's properties. Return `true` only when they contain your field type. Without this check, the hook runs for every collection on the instance, including indices your package does not own.

### Use the `$sigmie` argument

The `$sigmie` that `beforeBatch()` and `afterBatch()` get is a new instance on the same connection. It has no registered APIs and no hooks, so writes to a sidecar index through it do not run hooks.

### Read the `$apis` map

`processBatch()` and `afterBatch()` get the collection's APIs as a map of name to instance. `collect()` starts the map with the APIs from `Sigmie::registerApi()`. A call to `apis()` on the collection replaces the whole map.

`registerApi()` accepts only `EmbeddingsApi` and `RerankApi` instances. To give a hook an LLM client, inject it in the hook's constructor, or pass it to the collection's `apis()`:

```php
$embeddings = $apis['embeds'] ?? null;    // EmbeddingsApi
$rerank = $apis['my-rerank'] ?? null;     // RerankApi
$llm = $this->llmClient;                  // injected in the constructor
```

## Index without hooks

When the documents already hold the values a hook would add, call `withoutHooks()`. The collection then skips every hook:

```php
$sigmie->collect('kb')->withoutHooks()->merge($documents);
```

## Use the package in an application

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

Each `merge()` or `add()` on a collection with a `MagicTags` field now runs the hook. This applies only to collections from the instance you called `extend()` on.

## See also

- [Magic Tags](magic-tags.md), a package built on this API.
- [Mappings](mappings.md) for field types and properties.
- [Documents](document.md) for the collection lifecycle.
