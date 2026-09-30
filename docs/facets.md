---
title: Facets
short_description: Build faceted navigation with Sigmie — term, price histogram, stats, and date range facets, with disjunctive and conjunctive logic for e-commerce sidebars.
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

`caseSensitiveKeyword()` fields count each spelling separately:

```php
$props->caseSensitiveKeyword('tag');

$response->facet('tag');
// ['sport' => 2, 'Sport' => 1]
```

## Price facets

Price fields return min, max, and a histogram. The argument after `:` is the bucket size:

```php
$props->price();

$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->facets('price:100')        // $100 buckets
    ->get();

$price = $response->facet('price');
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

$stats = $response->facet('rating');
// [
//     'count' => 127,
//     'min' => 1.0,
//     'max' => 5.0,
//     'avg' => 4.3,
//     'sum' => 546.1,
// ]
```

## Date facets

Date fields (`date` and `datetime`) return the range they cover: the document `count` and the earliest and latest date. Use them to answer "which period does this data cover?":

```php
$props->date('published_at');

$response = $sigmie->newSearch('posts')
    ->properties($props)
    ->queryString('')
    ->facets('published_at') // [tl! highlight]
    ->get();

$response->facet('published_at');
// [
//     'count' => 3,
//     'min' => '2023-01-04T00:00:00.000Z',
//     'max' => '2025-11-30T00:00:00.000Z',
// ]
```

`min` and `max` are date strings in the field's first format, not epoch milliseconds. When no document matches, `count` is `0` and `min` and `max` are `null`. Facet filters on other fields narrow the range:

```php
$response = $sigmie->newSearch('posts')
    ->properties($props)
    ->queryString('')
    ->facets('published_at', "author:'Ada'")
    ->get();

$response->facet('published_at');
// ['count' => 2, 'min' => '2023-01-04T00:00:00.000Z', 'max' => '2024-06-18T00:00:00.000Z']
```

A date facet is a range, not a list of values. For counts per day, month, or year, use [analytics](analytics.md) `trend` or a `date_histogram` [aggregation](aggregations.md).

## Text facets

Text fields need a `.keyword` sub-field for faceting:

```php
$props->text('author')->keyword();

$response = $sigmie->newSearch('articles')
    ->properties($props)
    ->queryString('technology')
    ->facets('author')
    ->get();

$response->facet('author');
// ['Jane Doe' => 14, 'John Smith' => 9]
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

Like `filters()`, the facet filter string is parsed leniently — an invalid clause is dropped and recorded under the response's `errors` key. Pass `throwOnError: true` as the third argument to raise a `ParseException` instead:

```php
->facets('brand category', "brand:'Apple'", throwOnError: true)
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

Use dot notation. Nested facets return the same shape as root facets:

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

$response->facet('attributes.color');
// ['red' => 12, 'blue' => 7]

$response->facet('attributes.price');
// ['min' => 20, 'max' => 180, 'histogram' => [0 => 4, 50 => 9, 100 => 6, 150 => 2]]
```

`json('facets')` keys nested facets by their full path, `attributes.color`.

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

`facet()` returns one field's facet as an array, for root and nested fields alike:

```php
$price = $response->facet('price');                // ['min' => ..., 'max' => ..., 'histogram' => [...]]
$rating = $response->facet('rating');              // ['count' => ..., 'min' => ..., 'max' => ..., 'avg' => ..., 'sum' => ...]
$colors = $response->facet('attributes.color');    // ['red' => 12, 'blue' => 7]
```

`json('facets')` returns every requested facet, keyed by field path:

```php
$allFacets = $response->json('facets');
$brand = $response->json('facets.brand');
```

| Field type | Facet |
|------------|-------|
| `keyword`, `category`, `tags`, `caseSensitiveKeyword` | Value counts |
| `text()->keyword()` | Value counts of the keyword sub-field |
| `number` | `count`, `min`, `max`, `avg`, `sum` |
| `price` | `min`, `max`, `histogram` |
| `date`, `datetime` | `count`, `min`, `max` (date strings) |

> **Note:** `json('facets.attributes.color')` reads `.` as a path separator. Use `facet('attributes.color')` for nested fields.

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

$brand = $response->facet('brand');
$color = $response->facet('color');
$price = $response->facet('price');
$rating = $response->facet('rating');
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
