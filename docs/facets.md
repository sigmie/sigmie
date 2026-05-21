---
title: Facets
short_description: Faceted navigation and filter sidebars
keywords: [facets, filters, faceted search, navigation, refinement]
category: Features
order: 3
related_pages: [aggregations, search, filter-parser]
---

# Facets

Facets are the aggregated counts that drive filter sidebars: "Brand: Apple (12), Dell (8)" or "Price: $0–$100 (124), $100–$500 (89)". Sigmie generates them automatically from your property definitions — define a `category('brand')`, request `facets('brand')`, and you get back the counts.

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->category('brand');
$props->price();

$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->facets('brand price:100')
    ->get();

$facets = $response->json('facets');
// ['brand' => ['Apple' => 5, 'Dell' => 3], 'price' => [...]]
```

## Term facets

Category and keyword fields produce term counts:

```php
$props = new NewProperties;
$props->category('brand');
$props->keyword('color');

$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('shoes')
    ->facets('brand color')
    ->get();

$response->json('facets');
// ['brand' => ['Nike' => 15, 'Adidas' => 12], 'color' => ['black' => 10, ...]]
```

Use `category()` for categorical data (brand, department, genre). Use `keyword()` for exact-match strings (SKU, status).

## Price facets

Price fields return min, max, and a histogram. The argument after `:` is the bucket size:

```php
$props->price();

$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->facets('price:100')        // $100 buckets
    ->get();

$price = $props->get()['price']->facets($response->facetAggregations());
// [
//     'min' => 299,
//     'max' => 1499,
//     'histogram' => [
//         200 => 3,    // 3 in $200–$299
//         300 => 8,
//         400 => 5,
//         ...
//     ],
// ]
```

Pick interval size to match your data range — $10 for cheap items, $100 for big-ticket.

## Number facets

Number fields return statistics:

```php
$props->number('rating');

$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->facets('rating')
    ->get();

$stats = $props->get()['rating']->facets($response->facetAggregations());
// [
//     'count' => 127,
//     'min' => 1.0,
//     'max' => 5.0,
//     'avg' => 4.3,
//     'sum' => 546.1,
// ]
```

## Text facets

Text fields need a `.keyword` sub-field for faceting:

```php
$props->text('author')->keyword();

$response = $sigmie->newSearch('articles')
    ->properties($props)
    ->queryString('technology')
    ->facets('author')
    ->get();
```

## Filtering with facets

### Global filters

`filters()` applies to both results and facet counts:

```php
$sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->filters("brand:'Apple' AND price:500..1500")
    ->facets('brand category price:100')
    ->get();
```

### Facet-specific filters

Pass a filter string as the second argument to `facets()`:

```php
$sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->facets('brand category color', "brand:'Apple' AND category:'electronics'")
    ->get();
```

## Disjunctive vs conjunctive

E-commerce facets usually want **disjunctive** logic: selecting two brands should show items from either brand, and both brand options should remain visible in the sidebar.

### Disjunctive (OR within a field)

```php
$props->category('color')->facetDisjunctive();
$props->category('size')->facetDisjunctive();

$sigmie->newSearch('products')
    ->properties($props)
    ->queryString('shirt')
    ->facets('color size', "color:'red' color:'blue' size:'lg'")
    ->get();
```

- Multiple values for the same field combine with **OR**: `color:red OR color:blue`.
- Different fields combine with **AND**: `(color) AND (size)`.
- Both red and blue stay visible in color facets.

### Conjunctive (AND within a field)

```php
$props->category('color')->facetConjunctive();
$props->category('material')->facetConjunctive();
```

Multiple values combine with **AND**: only items matching every selected value are returned. Use this when filters narrow a set of multi-valued documents (a product with multiple tags).

### Self-exclusion

With disjunctive facets, a field's own filter doesn't affect that field's facet counts — so selecting "Apple" still shows you how many Dell, HP, Lenovo items exist:

```php
$sigmie->newSearch('products')
    ->properties($props)
    ->filters("stock>0")
    ->facets('color size', "color:'green'")
    ->get();
```

- Color facets show **every** color available (not just green).
- Size facets reflect sizes available for green items.
- Results contain only green items.

This is the standard pattern for filter UIs.

## Nested fields

Use dot notation:

```php
$props->nested('attributes', function (NewProperties $p) {
    $p->keyword('color');
    $p->price();
});

$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('shirt')
    ->facets('attributes.color attributes.price:50')
    ->get();

$compiled = $props->get();
$colors = $compiled->get('attributes.color')->facets($response->facetAggregations());
```

Multi-level nesting works too:

```php
$props->nested('product', function (NewProperties $p) {
    $p->nested('variants', function (NewProperties $p) {
        $p->keyword('size');
        $p->price();
    });
});

->facets('product.variants.size product.variants.price:25')
```

## Reading facet data

### From the response JSON

```php
$allFacets = $response->json('facets');
$brand = $response->json('facets.brand');
$color = $response->json('facets.color');
```

### Through property objects

For price and number facets, the property object computes structured data:

```php
$compiled = $props->get();

$price = $compiled['price']->facets($response->facetAggregations());
$min = $price['min'];
$max = $price['max'];
$histogram = $price['histogram'];

$rating = $compiled['rating']->facets($response->facetAggregations());
$avg = $rating['avg'];
$count = $rating['count'];
```

## Combined example

A realistic e-commerce facet setup:

```php
$props = new NewProperties;
$props->category('category')->facetDisjunctive();
$props->category('brand')->facetDisjunctive();
$props->category('color')->facetDisjunctive();
$props->category('size')->facetDisjunctive();
$props->price();
$props->number('rating');
$props->number('stock');

$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString($searchTerm)
    ->filters("stock>0")
    ->facets(
        'category brand color size price:50 rating',
        "category:'electronics' brand:'apple' price:500..1500"
    )
    ->get();

$compiled = $props->get();
$brand = $response->json('facets.brand');
$color = $response->json('facets.color');
$price = $compiled['price']->facets($response->facetAggregations());
$rating = $compiled['rating']->facets($response->facetAggregations());
```

## Empty search with facets

Browsing without a query string:

```php
$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('')
    ->facets('category brand price:100')
    ->get();
```

Returns facets across the entire dataset.

## See also

- [Aggregations](aggregations.md) — raw `terms`, `range`, `histogram`, `stats` aggregations.
- [Filter Parser](filter-parser.md) — the syntax used in `filters()` and `facets()`.
- [Mappings & Properties](mappings.md) — `facetDisjunctive()` / `facetConjunctive()` on field definitions.
