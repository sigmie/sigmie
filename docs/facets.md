---
title: Facets
short_description: Reference for Sigmie facets on Elasticsearch. Facet string syntax, facet filters, conjunctive and disjunctive logic, and the result shape per field type.
keywords: [facets, filters, faceted search, navigation, refinement]
category: Features
order: 3
related_pages: [aggregations, search, filter-parser]
---

# Facets

Facets are the counts and ranges beside search results, such as "Brand: Apple (12), Dell (8)". `facets()` on a search builds them from your property definitions and returns them under the response's `facets` key.

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->category('brand');
$props->price();

$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->facets('brand price:100') // [tl! highlight]
    ->get();

$response->json('facets');
// ['brand' => ['Apple' => 5, 'Dell' => 3], 'price' => ['min' => 299, 'max' => 1499, 'histogram' => [...]]]
```

With an empty query string, the facets cover every document that the filters allow.

## The facets() method

```php
facets(string $facets, string $filters = '', bool $throwOnError = false)
```

| Parameter | Description |
|-----------|-------------|
| `$facets` | Space-separated list of fields to facet. See [Facet string](#facet-string). |
| `$filters` | The values the user selected. See [Facet filter string](#facet-filter-string). |
| `$throwOnError` | `false` drops an invalid clause and records it under the response's `errors` key. `true` throws a `ParseException`. |

```php
->facets('brand category', "brand:'Apple'", throwOnError: true)
```

## Facet string

Each entry is a field path with an optional parameter after `:`.

| Entry | Field types | Meaning |
|-------|-------------|---------|
| `brand` | All | Facet with default parameters. |
| `brand:20` | Term fields | Return the top 20 values. Default 10. |
| `brand:20,asc` | Term fields | Return the top 20 values, sorted by value. Also `desc`. |
| `price:100` | `price` | Histogram bucket width of 100. Default 10. |
| `attributes.color` | Nested | Facet a field inside a nested object. |

`number`, `date`, and `datetime` facets take no parameter.

## Facet filter string

The facet filter string holds the values the user selected. It uses [filter parser](filter-parser.md) clauses separated by spaces:

```php
->facets('color size', "color:'red' color:'blue' size:'lg' price:100..200")
```

Sigmie groups the clauses by field. It joins the clauses of one field with the field's [facet logic](#facet-logic), then joins the fields with `AND`. When `color` is disjunctive, the string above becomes:

```
(color:'red' OR color:'blue') AND (size:'lg') AND (price:100..200)
```

When `color` is conjunctive, the first group is `(color:'red' AND color:'blue')`.

The facet filter string does not accept `AND`, `OR`, `AND NOT`, or parentheses. If it contains one, Sigmie drops the whole facet filter string and records the error, or throws when `$throwOnError` is `true`.

### How facet filters apply

The facet filter narrows the hits. For the counts, each facet ignores the clauses on its own field and applies the clauses on every other field. A user who selects `color:'green'` still sees every color with its count, and the size facet shows only the sizes that exist in green:

```php
$sigmie->newSearch('products')
    ->properties($props)
    ->filters('stock>0')
    ->facets('color size', "color:'green'")
    ->get();
```

| | `filters()` | Facet filter string |
|-|-------------|---------------------|
| Narrows the hits | Yes | Yes |
| Narrows the counts of the same field | Yes | No |
| Narrows the counts of other fields | Yes | Yes |
| Accepts `AND`, `OR`, parentheses | Yes | No |

Use `filters()` for constraints the user does not pick in the sidebar, such as `stock>0`. See [Search](search.md#filter).

## Facet logic

Facet logic sets how several selected values of one field combine. Set it on the property:

```php
$props->category('color')->facetDisjunctive(); // [tl! highlight]
$props->category('material')->facetConjunctive();
```

| Logic | Values of one field | Use for |
|-------|---------------------|---------|
| Conjunctive (default) | Joined with `AND`. A hit must have every selected value. | Multi-value fields, such as tags. |
| Disjunctive | Joined with `OR`. A hit must have one of the selected values. | Single-value fields, such as brand or size. |

Different fields always combine with `AND`.

## Results by field type

| Field type | Facet result |
|------------|--------------|
| `keyword`, `category`, `tags`, `caseSensitiveKeyword` | Value counts |
| `text()->keyword()` | Value counts of the keyword sub-field |
| `number` | `count`, `min`, `max`, `avg`, `sum` |
| `price` | `min`, `max`, `histogram` |
| `date`, `datetime` | `count`, `min`, `max` as date strings |

A `text` field without `->keyword()` cannot be faceted.

### Term fields

`keyword`, `category`, and `tags` fields return each value with its count:

```php
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

A `caseSensitiveKeyword` field counts each spelling on its own:

```php
$props->caseSensitiveKeyword('tag');

$response->facet('tag');
// ['sport' => 2, 'Sport' => 1]
```

A `text` field with a keyword sub-field returns the counts of that sub-field:

```php
$props->text('author')->keyword();

$response->facet('author');
// ['Jane Doe' => 14, 'John Smith' => 9]
```

### Price fields

A `price` field returns the lowest price, the highest price, and a histogram. Each histogram key is the lower bound of a bucket:

```php
$props->price();

$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->facets('price:100') // [tl! highlight]
    ->get();

$response->facet('price');
// [
//     'min' => 299,
//     'max' => 1499,
//     'histogram' => [
//         200 => 3,    // 3 products from 200 to 299.99
//         300 => 8,
//         400 => 5,
//         ...
//     ],
// ]
```

### Number fields

A `number` field returns statistics:

```php
$props->number('rating');

$response->facet('rating');
// [
//     'count' => 127,
//     'min' => 1.0,
//     'max' => 5.0,
//     'avg' => 4.3,
//     'sum' => 546.1,
// ]
```

### Date fields

A `date` or `datetime` field returns the period it covers: the document `count`, the earliest date, and the latest date.

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

A date facet is a range, not a list of values. For counts per day, month, or year, use the [analytics](analytics.md) `trend` widget or a `dateHistogram` [aggregation](aggregations.md).

## Nested fields

Facet a field inside a `nested` property with its dot path. The result has the same shape as a root field of the same type:

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

Nesting can go deeper, for example `product.variants.size`.

## Read facet results

`facet()` returns the facet of one field, root or nested. It returns `null` for a field that is not in the properties or cannot be faceted.

```php
$response->facet('price');            // ['min' => ..., 'max' => ..., 'histogram' => [...]]
$response->facet('rating');           // ['count' => ..., 'min' => ..., 'max' => ..., 'avg' => ..., 'sum' => ...]
$response->facet('attributes.color'); // ['red' => 12, 'blue' => 7]
```

`json('facets')` returns every requested facet, keyed by field path:

```php
$response->json('facets');       // ['brand' => [...], 'attributes.color' => [...]]
$response->json('facets.brand');
```

> **Note:** `json()` reads `.` as a path separator, so `json('facets.attributes.color')` does not find a nested facet. Use `facet('attributes.color')`.
