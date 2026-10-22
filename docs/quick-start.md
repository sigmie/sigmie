---
title: Quick start
short_description: Build a product search with Sigmie and Elasticsearch in one PHP script. Create an index, add products, then search with typos, filters, sorting, and facets.
keywords: [quick start, tutorial, first search, getting started]
category: Getting Started
order: 3
related_pages: [installation, introduction, search, document]
---

# Quick start

In this tutorial, we build a search for a catalog of three products. By the end, it finds a product from a misspelled word, filters by stock and price, sorts by price, and counts products per category.

All code goes in one file, `search.php`. Each step adds code to the end of the file, and you run the whole file again.

## Before you start

You need:

- Sigmie in a Composer project. See [Installation](installation.md).
- Elasticsearch on `127.0.0.1:9200` with security off. The Docker command in [Start Elasticsearch locally](installation.md#start-elasticsearch-locally) gives you one.

## Step 1: Connect

Create `search.php` in your project root:

```php
<?php

require_once 'vendor/autoload.php';

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;

$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);

var_dump($sigmie->isConnected());
```

Run it:

```bash
php search.php
```

The output is:

```
bool(true)
```

If you see `bool(false)`, Elasticsearch is not reachable. Check that the node runs on port 9200.

## Step 2: Create the index and add products

First, we describe the fields. `NewProperties` holds the field types, and each type decides how Elasticsearch stores and searches the field. Add this to `search.php`:

```php
$props = new NewProperties;
$props->title('name');
$props->text('description');
$props->category('category');
$props->price();
$props->bool('in_stock');
```

Now, we create the `products` index from those properties and add three products. `deleteIfExists()` removes the index from an earlier run, so you can run the script again:

```php
$sigmie->deleteIfExists('products');

$sigmie->newIndex('products')
    ->properties($props)
    ->create();

$sigmie->collect('products', refresh: true)
    ->properties($props)
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

echo $sigmie->collect('products')->count(), "\n";
```

`refresh: true` makes the products searchable as soon as `merge()` returns. Leave it out for large imports. See [Documents](document.md).

Run the script. The output now ends with:

```
3
```

## Step 3: Search for a word

We print product names in every step from here on. Add a small helper and the first search:

```php
function printNames($results): void
{
    foreach ($results->hits() as $hit) {
        echo $hit['name'], "\n";
    }
}

echo "-- laptop\n";

printNames($sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->get());
```

Run the script. The new output is:

```
-- laptop
Laptop Pro
```

## Step 4: Find a misspelled word

Users mistype. Search for `laptp`, with typo tolerance on the `name` field:

```php
echo "-- laptp\n";

printNames($sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptp')
    ->typoTolerance()                    // [tl! highlight]
    ->typoTolerantAttributes(['name'])   // [tl! highlight]
    ->get());
```

Run the script. The new output is:

```
-- laptp
Laptop Pro
```

Without the two highlighted lines, this search finds nothing. `typoTolerance()` sets the rules: one typo in words of 3 or more characters, two typos in words of 6 or more. `typoTolerantAttributes()` names the fields the rules apply to.

## Step 5: Filter by stock and price

A filter string limits the results without changing their order. An empty query string matches every product:

```php
echo "-- in stock, 100 or less\n";

printNames($sigmie->newSearch('products')
    ->properties($props)
    ->queryString('')
    ->filters('in_stock:true AND price<=100')   // [tl! highlight]
    ->get());
```

Run the script. The new output is:

```
-- in stock, 100 or less
Wireless Mouse
```

The cable costs less than 100, but it is out of stock. The [filter parser](filter-parser.md) page lists every operator.

## Step 6: Sort by price

```php
echo "-- cheapest first\n";

printNames($sigmie->newSearch('products')
    ->properties($props)
    ->queryString('')
    ->sort('price:asc')   // [tl! highlight]
    ->get());
```

Run the script. The new output is:

```
-- cheapest first
USB-C Cable
Wireless Mouse
Laptop Pro
```

Without `sort()`, Sigmie sorts by `_score`, highest first. Sigmie rejects `_score:asc`. The [sort parser](sort-parser.md) page lists the sort syntax.

## Step 7: Count products per category

Facets return counts for a filter sidebar. Ask for category counts and the price range:

```php
echo "-- facets\n";

$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('')
    ->facets('category price:100')   // [tl! highlight]
    ->get();

$facets = $results->json('facets');

print_r((array) $facets['category']);

$price = (array) $facets['price'];

echo "Price: {$price['min']} to {$price['max']}\n";
```

Run the script. The new output is:

```
-- facets
Array
(
    [accessories] => 2
    [electronics] => 1
)
Price: 15 to 1299
```

`price:100` sets a histogram interval of 100. The price facet also holds that histogram under `histogram`.

## What we built

`search.php` now creates an index, adds three products, and runs a word search, a typo-tolerant search, a filtered search, a sorted search, and a facet count. Every step used the same `newSearch()` chain, with one more method each time.

## Next steps

- [Search](search.md) covers field weights, highlighting, pagination, and every other `newSearch()` method.
- [Facets](facets.md) explains facets that keep counts for other values after a user selects one.
- [Semantic search](semantic-search.md) finds products by meaning with an embeddings API.
- [Mappings and properties](mappings.md) lists every field type.
- [Laravel Scout](laravel-scout.md) connects Sigmie to Eloquent models.
