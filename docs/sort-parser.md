---
title: Sort Parser
short_description: Sigmie sort syntax reference for Elasticsearch, covering _score rating:desc name:asc strings, sortable text fields, nested fields, and geo distance.
keywords: [sort parser, sorting, order, search results]
category: Utilities
order: 2
related_pages: [search, query, filter-parser]
---

# Sort Parser

The sort parser turns a sort string such as `_score rating:desc name:asc` into an Elasticsearch `sort` array. It checks every field name against your properties.

Pass a sort string to `sort()` on `newSearch()`, or to `sortString()` on `newQuery()`:

```php
$sigmie->newSearch('movies')
    ->properties($props)
    ->sort('_score rating:desc name:asc'); // [tl! highlight]

$sigmie->newQuery('movies')
    ->properties($props)
    ->sortString('_score rating:desc name:asc'); // [tl! highlight]
```

Each call replaces the previous sort. For the search builders, see [Sort](search.md#sort) and [Sorting](query.md#sorting).

To build the array without a search, create a `SortParser`:

```php
use Sigmie\Mappings\NewProperties;
use Sigmie\Parse\SortParser;

$props = new NewProperties;
$props->number('rating');
$props->text('name')->makeSortable();

$parser = new SortParser($props());
$sort = $parser->parse('_score rating:desc name:asc');
```

`$sort` holds:

```json
[
    "_score",
    { "rating": "desc" },
    { "name.sortable": "asc" }
]
```

## Syntax

```
_score:desc rating:desc name:asc
```

One space separates the clauses. Each clause is one of these forms:

| Clause | Sorts by |
|--------|----------|
| `_score` or `_score:desc` | Relevance, highest first. |
| `_doc` | Index order. |
| `field` | The field, ascending. |
| `field:asc` | The field, ascending. |
| `field:desc` | The field, descending. |
| `field[lat,lon]:unit:direction` | Distance from a point. See [Geo distance](#geo-distance). |

An empty string sorts by `_score`.

> **Note:** `_score:asc` is not allowed, because Elasticsearch cannot sort relevance ascending. Use `_score` or `_score:desc`.

## Field types

The parser picks the sort field from the property type:

| Property | Sort field |
|----------|------------|
| `keyword()` | The field itself. |
| `text()` with `makeSortable()` | The `.sortable` sub-field. |
| `text()` without `makeSortable()` | Error: `Field <name> is not sortable.` |
| `name()`, `title()`, `category()`, `email()`, `address()`, `searchableNumber()` | The `.sortable` sub-field. These types add it themselves. |
| `id()` | `id.sortable`. |
| Other types, such as `number()`, `date()`, and `bool()` | The field itself. |

A field inside a `nested()` property sorts with the nested path:

```json
{ "contact.name.sortable": { "nested": { "path": "contact" }, "order": "asc" } }
```

## Geo distance

A `geoPoint()` field sorts by its distance from a point:

```
location[40.71,-74.00]:km:asc
```

The format is `field[lat,lon]:unit:direction`. All four parts are required.

| Part | Values |
|------|--------|
| `lat` | -90 to 90 |
| `lon` | -180 to 180 |
| `unit` | `km`, `m`, `cm`, `mm`, `mi`, `yd`, `ft`, `in`, `nmi` |
| `direction` | `asc`, `desc` |

## Errors

The parser reports an error for:

- A field that is not in the properties.
- A `text` field without `makeSortable()`.
- `_score:asc`, or any direction on `_score` other than `desc`.
- A direction written after a space, such as `name asc`. Write `name:asc`.
- A geo clause on a field that is not a `geoPoint()`, or with an invalid unit, direction, or coordinate.

`new SortParser($props)` throws a `ParseException` on the first error. With `new SortParser($props, throwOnError: false)`, the parser skips the invalid clause and adds the error to `errors()`.

| Caller | Default |
|--------|---------|
| `new SortParser($props)` | Throws |
| `newSearch()->sort()` | Throws. Pass `throwOnError: false` to skip invalid clauses. |
| `newQuery()->sortString()` | Throws |
