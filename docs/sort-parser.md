---
title: Sort Parser
short_description: Sort expression syntax for searches and queries
keywords: [sort parser, sorting, order, search results]
category: Utilities
order: 2
related_pages: [search, query, filter-parser]
---

# Sort Parser

The Sort Parser turns space-separated sort expressions into Elasticsearch sort arrays. You write `_score rating:desc name:asc`; Sigmie generates the right JSON.

## In `newSearch()` and `newQuery()`

Pass a sort string to `sort()` on `NewSearch`, or `sortString()` on `NewQuery`:

```php
$sigmie->newSearch('movies')
    ->properties($props)
    ->sort('_score rating:desc name:asc');

$sigmie->newQuery('movies')
    ->properties($props)
    ->sortString('_score rating:desc name:asc');
```

> **Note:** On `NewQuery`, call `sortString()` **before** the query method (`matchAll`, `bool`, `parse`, etc.). Each call replaces the previous sort.

## Syntax

```
_score:desc rating:desc name:asc
```

Each clause is `field` or `field:asc` / `field:desc`. Clauses are space-separated. The default direction depends on the field: `_score` defaults to `desc`, everything else to `asc`.

> **Note:** `_score:asc` is **not allowed**. Elasticsearch can't sort relevance ascending. Use `_score` or `_score:desc`.

## With properties

When you pass properties, the parser routes text fields to their `.keyword` sub-field automatically:

```php
$props = new NewProperties;
$props->bool('active');
$props->text('name')->keyword();
$props->text('category');

$parser = new SortParser($props());
$parser->parse('_score rating:desc name:asc');
```

The compiled output:

```json
[
    "_score",
    { "rating": "desc" },
    { "name.keyword": "asc" }
]
```

Without properties, the parser passes field names through unchanged — which usually fails for text fields, since Elasticsearch can't sort an analyzed `text` field directly. Always pass properties when sorting on text.

## Direct use

```php
use Sigmie\Parse\SortParser;

$parser = new SortParser($props());
$sort = $parser->parse('_score rating:desc name:asc');
```

The result is a valid Elasticsearch sort array suitable for the `sort` key in a raw query body.

## Geo sort

For `geoPoint` fields:

```
location[40.71,-74.00]:km:asc
```

The format is `field[lat,lon]:unit:direction`. Units match the filter parser: `km`, `mi`, `m`, `yd`, etc.

## See also

- [Filter Parser](filter-parser.md) — same human-friendly syntax for `WHERE` clauses.
- [Search](search.md#sort) — using sort with `newSearch()`.
- [Advanced Queries](query.md#sorting) — using sort with `newQuery()`.
