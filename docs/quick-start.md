---
title: Quick Start
short_description: Build your first Sigmie search in five minutes
keywords: [quick start, tutorial, first search, getting started]
category: Getting Started
order: 3
related_pages: [installation, introduction, search, document]
---

# Quick Start

In five minutes you'll have a product search that handles typos, filters by stock and price, and returns relevance-ranked results.

## Prerequisites

- Sigmie [installed](installation.md)
- Elasticsearch running on `127.0.0.1:9200`

Quick sanity check:

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);

$sigmie->isConnected();   // true
```

## Step 1: Define a schema

`NewProperties` is your schema builder. Use high-level types — they wire up the right analyzers and queries underneath.

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->title('name');         // full-text searchable title
$props->text('description');   // long-form searchable text
$props->category('category');  // exact-match category
$props->price();               // numeric, filterable by range
$props->bool('in_stock');
```

## Step 2: Create the index

```php
$sigmie->newIndex('products')
    ->properties($props)
    ->create();
```

## Step 3: Index documents

```php
use Sigmie\Document\Document;

$sigmie->collect('products', refresh: true)
    ->merge([
        new Document([
            'name' => 'Laptop Pro',
            'description' => 'High-performance laptop for professionals',
            'category' => 'electronics',
            'price' => 1299,
            'in_stock' => true,
        ]),
        new Document([
            'name' => 'Wireless Mouse',
            'description' => 'Ergonomic wireless mouse with precision tracking',
            'category' => 'accessories',
            'price' => 49,
            'in_stock' => true,
        ]),
        new Document([
            'name' => 'USB-C Cable',
            'description' => 'Fast charging and data transfer cable',
            'category' => 'accessories',
            'price' => 15,
            'in_stock' => false,
        ]),
    ]);
```

`refresh: true` makes documents immediately searchable. Omit it in production for better bulk-indexing performance.

## Step 4: Search

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->get();

foreach ($results->hits() as $hit) {
    echo $hit['name'] . "\n";
}
// Laptop Pro
```

## Step 5: Tolerate typos

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('lapto')                        // typo
    ->typoTolerance()                             // [tl! highlight]
    ->get();
// Laptop Pro
```

Defaults: one typo allowed for terms of 3+ characters, two typos for 6+. Override with `typoTolerance(oneTypoChars: 4, twoTypoChars: 8)`.

## Step 6: Filter

The [filter parser](filter-parser.md) reads like a sentence:

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('cable')
    ->filters('in_stock:true')                    // [tl! highlight]
    ->get();
// (no results — USB-C Cable is out of stock)
```

Combine clauses with `AND`, `OR`, and `NOT`:

```php
->filters('category:"accessories" AND price:<=100')
->filters('price:100..500 AND in_stock:true')
->filters('NOT category:"books"')
```

## Step 7: Sort and paginate

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('mouse cable')
    ->sort('_score:desc price:asc')
    ->from(0)
    ->size(10)
    ->get();
```

`_score:desc` is the default. `_score:asc` is not allowed — Elasticsearch always sorts relevance highest-first.

## Step 8: Weight fields

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->weight(['name' => 3, 'description' => 1])   // [tl! highlight]
    ->get();
```

A match in `name` now scores 3× higher than the same match in `description`.

## Putting it together

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('wireless laptop')
    ->typoTolerance()
    ->filters('category:"electronics" AND price:100..2000 AND in_stock:true')
    ->weight(['name' => 3, 'description' => 1])
    ->sort('_score:desc price:asc')
    ->size(20)
    ->get();

echo "Found {$results->total()} products\n";

foreach ($results->hits() as $hit) {
    printf("%s — $%d\n", $hit['name'], $hit['price']);
}
```

## Add facets

Faceted navigation is one method away:

```php
$props->category('brand')->facetDisjunctive();   // enable faceting

$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->facets('brand category price:100')         // [tl! highlight]
    ->get();

$facets = $results->json('facets');
// ['brand' => ['Apple' => 5, ...], 'price' => ['min' => 999, 'max' => 2499, ...]]
```

See [Facets](facets.md) for the full reference.

## Add semantic search

When you want results by meaning, not just keywords:

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;

$sigmie->registerApi('embeddings', new OpenAIEmbeddingsApi('sk-...'));

$props = new NewProperties;
$props->title('name');
$props->text('description')->semantic(            // [tl! highlight]
    api: 'embeddings',
    dimensions: 1536,
);

$results = $sigmie->newSearch('products')
    ->properties($props)
    ->semantic()
    ->queryString('portable computer for work')   // matches "laptop", "notebook"
    ->get();
```

See [Semantic Search](semantic-search.md) for embeddings setup, accuracy levels, and similarity functions.

## Where to go next

- [Filter Parser](filter-parser.md) — every operator and clause.
- [Facets](facets.md) — sidebar filters with conjunctive/disjunctive logic.
- [Search](search.md) — every `NewSearch` method.
- [Mappings & Properties](mappings.md) — all field types.
- [Laravel Scout](laravel-scout.md) — Eloquent integration.
