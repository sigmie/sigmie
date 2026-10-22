---
title: Advanced Queries
short_description: Sigmie newQuery() reference for Elasticsearch bool clauses, term, match, range, and wildcard queries, script scoring, aggregations, and sorting.
keywords: [query, advanced query, bool query, elasticsearch dsl, custom queries]
category: Core Concepts
order: 6
related_pages: [search, document, semantic-search, sort-parser]
---

# Advanced Queries

`newQuery()` builds an Elasticsearch query clause by clause. Use it for queries that [`newSearch()`](search.md) does not build, such as custom boolean logic and script scoring. `newSearch()` has typo tolerance, highlighting, facets, and semantic search. `newQuery()` has none of these.

```php
use Sigmie\Query\Queries\Compound\Boolean;

$response = $sigmie->newQuery('disney-movies')
    ->properties($props)
    ->sortString('name:asc')
    ->bool(function (Boolean $bool) {
        $bool->filter()->term('is_active', true);
        $bool->must()->multiMatch(['name', 'description'], 'goofy');
        $bool->mustNot()->wildcard('name', 'test*');
        $bool->should()->bool(fn (Boolean $bool) =>
            $bool->must()->match('name', 'Mickey')
        );
    })
    ->from(0)
    ->size(15)
    ->get();
```

## Builder

`$sigmie->newQuery($index)` returns a `NewQuery`. Call the setup methods first, then one query method. The query method returns a `Search`, which takes the request options and runs the request.

```
newQuery('movies')            NewQuery
  ->properties($props)          setup
  ->sortString('year:desc')     setup
  ->bool(...)                 query method, returns Search
  ->size(20)                    request option
  ->get()                     runs the request
```

| `NewQuery` method | Does |
|-------------------|------|
| `properties($props)` | Sets the properties that `parse()`, `sortString()`, and `postFilterString()` read. |
| `sortString(string $sort)` | Sets the sort from a [sort string](sort-parser.md). |
| `sort(array $sort)` | Sets the sort from an Elasticsearch `sort` array. |
| `chunk(int $size = 500)` | Sets the page size for `each()` and `lazy()`. |
| `each(Closure $fn)`, `lazy()` | Read every matching hit. See [Iterating over all matching hits](search.md#iterating-over-all-matching-hits). |

The query methods, such as `term()` and `match()`, take Elasticsearch field names as they are. Only `parse()`, `sortString()`, and `postFilterString()` read the properties.

## Boolean clauses

`bool()` passes a `Boolean` to your function. Each of its four methods adds clauses of one kind:

| Clause | Matches when | Affects `_score` |
|--------|--------------|------------------|
| `must()` | Every clause matches. | Yes |
| `filter()` | Every clause matches. | No |
| `mustNot()` | No clause matches. | No |
| `should()` | See below. | Yes |

```php
$sigmie->newQuery('movies')
    ->properties($props)
    ->bool(function (Boolean $bool) {
        $bool->must()->match('title', 'matrix');
        $bool->filter()->range('year', ['>' => 1990]);
        $bool->should()->term('genre', 'sci-fi');
        $bool->mustNot()->term('rating', 'R');
    })
    ->get();
```

`should()` clauses follow the Elasticsearch rule. Without `must()` or `filter()` clauses, at least one `should()` clause must match. With them, `should()` clauses only raise the score. To require a number of `should()` matches, set `minimum_should_match`:

```php
$bool->addRaw('minimum_should_match', 1);
```

Use `filter()` instead of `must()` when a clause must not change the score. Elasticsearch can cache filter clauses.

A clause builder accepts the query methods below, plus these:

| Method | Adds |
|--------|------|
| `bool(callable $fn, float $boost = 1)` | A nested `bool` query. |
| `query(QueryClause $query)` | Any query object. |
| `parse(string $filter)` | The query from a [filter string](filter-parser.md). Returns nothing, so do not chain after it. |

## Query methods

Each method is available on `NewQuery` and on the clause builders. On `NewQuery`, the method sets the whole query and returns a `Search`. Every method also takes an optional `float $boost = 1`, except `term()` on `NewQuery`.

| Method | Elasticsearch query |
|--------|---------------------|
| `matchAll()` | `match_all` |
| `matchNone()` | `match_none` |
| `term(string $field, $value)` | `term` |
| `terms(string $field, array $values)` | `terms` |
| `match(string $field, string $query)` | `match` |
| `multiMatch(array $fields, string $query)` | `multi_match` |
| `range(string $field, array $values)` | `range` |
| `exists(string $field)` | `exists` |
| `ids(array $ids)` | `ids` |
| `regex(string $field, string $regex)` | `regexp` |
| `wildcard(string $field, string $value)` | `wildcard` |
| `fuzzy(string $field, string $value)` | `fuzzy` |

`NewQuery` also has `bool()`, `query()`, and `parse()`, with the same meaning as on the clause builders. `postFilter(QueryClause $query)` and `postFilterString(string $filter)` set a post filter.

### Term and terms

`term()` matches one exact value. Use it on `keyword`, `bool`, and number fields:

```php
$query->term('active', true);
$query->term('user_id', 13);
```

`terms()` matches any of several values:

```php
$query->terms('category', ['horror', 'action']);
```

> **Note:** Elasticsearch splits a `text` field into tokens, so `term()` on it rarely matches. Add a `keyword()` sub-field and query that:
>
> ```php
> $props->text('category')->keyword();
> // then:
> $query->term('category.keyword', 'drama');
> ```

### Match and multi-match

`match()` analyzes the query text first. Use it on `text` fields:

```php
$query->match('name', 'mickey');
$query->multiMatch(['name', 'username'], 'mickey');
```

### Range

```php
$query->range('count', ['>=' => 233]);
$query->range('price', ['>=' => 30, '<=' => 130]);
```

The operators are `>=`, `>`, `<=`, and `<`.

### Exists and IDs

`exists()` matches documents with any value in the field. `ids()` matches documents by `_id`:

```php
$query->exists('director');
$query->ids(['dkKwMe4UBAUb2dMteRe2', 'wd6Me4UBAUb2dMJT']);
```

### Regex, wildcard, and fuzzy

```php
$query->regex('category', '(horror|action)');
$query->wildcard('name', 'john*');
$query->fuzzy('name', 'john');
```

### Boost

`boost` scales a clause's share of `_score`:

```php
$query->matchAll(boost: 5);
```

## Filter strings

`parse()` builds the query from a [filter string](filter-parser.md). It reads the properties and throws a `ParseException` on any error:

```php
$sigmie->newQuery('movies')
    ->properties($props)
    ->parse('name:"John Doe" AND age<21')
    ->get();
```

## Script scoring

Wrap a query in a `FunctionScore` to score it with a Painless script, then pass it to `query()`:

```php
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\MatchAll;

$sigmie->newQuery('movies')
    ->query(new FunctionScore(
        new MatchAll,
        source: "Math.log(2 + doc['popularity'].value)",
        boostMode: 'replace',
    ))
    ->get();
```

`boostMode` defaults to `multiply`. `params` passes values to the script.

## Aggregations

`aggregate()` on the returned `Search` adds aggregations:

```php
use Sigmie\Query\Aggs;

$response = $sigmie->newQuery('orders')
    ->matchAll()
    ->aggregate(function (Aggs $aggs) {
        $aggs->sum(name: 'turnover', field: 'price');
    })
    ->get();

$response->aggregation('turnover.value');
```

See [Aggregations](aggregations.md) for every aggregation type. `newQuery()` has no `facets()` method. For facets, use [`newSearch()`](search.md#add-facets).

## Sorting

`sortString()` takes a [sort string](sort-parser.md). `sort()` takes an Elasticsearch `sort` array:

```php
$query->sortString('name:asc created_at:desc');
$query->sort([['year' => 'desc'], ['_score' => 'desc']]);
```

Both are on `NewQuery` and on the returned `Search`. Each call replaces the previous sort, so put every field in one call. `sortString()` throws a `ParseException` on an invalid sort, such as `_score:asc`.

> **Note:** A `text` field sorts only with a `.sortable` sub-field. Add it with `$props->text('name')->makeSortable()`.

## Request options

These methods are on the `Search` that the query method returns:

| Method | Does | Default |
|--------|------|---------|
| `from(int $from)` | Skips hits. | `0` |
| `size(int $size)` | Sets the number of hits. | `500` |
| `minScore(float $score)` | Drops hits below the score. | `0` |
| `fields(array $fields)` | Returns only these `_source` fields. | All |
| `except(array $fields)` | Drops these `_source` fields. | None |
| `highlight(array $highlight)` | Sets the Elasticsearch `highlight` body. | None |
| `trackTotalHits(bool\|int $trackTotalHits = true)` | Sets `track_total_hits`. | Not sent |
| `addRaw(string $key, mixed $value)` | Adds a top-level key to the request body. | None |

```php
$response = $sigmie->newQuery('movies')
    ->properties($props)
    ->sortString('title:asc')
    ->matchAll()
    ->from(0)
    ->size(20)
    ->get();
```

## Responses

`get()` runs the request and returns the response. `promise()` returns a Guzzle promise instead.

```php
$response->json();                       // full response
$response->json('hits.hits');            // hits array
$response->total();                      // hits.total.value
$response->hits();                       // array of Hit objects
$response->aggregation('turnover.value');
```

## Debugging

`getDSL()` on the `Search` returns the request body as an array:

```php
$dsl = $sigmie->newQuery('movies')->matchAll()->getDSL();
```
