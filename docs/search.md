---
title: Search
short_description: Build user-facing Elasticsearch searches with Sigmie — typo tolerance, faceted navigation, highlighting, semantic search, and filter-parser syntax.
keywords: [search, query, filters, sorting, highlighting, typo tolerance]
category: Core Concepts
order: 5
related_pages: [query, document, semantic-search, filter-parser]
---

# Search

`newSearch()` is the high-level entry point for user-facing search: typo tolerance, faceting, highlighting, weighting, semantic matching, all in one fluent chain.

For lower-level access to Elasticsearch's boolean query DSL, see [Advanced Queries](query.md).

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->name();
$props->text('description');

$results = $sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('snow white')
    ->get();
```

Two arguments are required: the **properties** (so Sigmie knows how to query each field) and the **query string**.

## Query string

The user input you're searching for:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('snow white')
    ->get();
```

Add multiple query strings with different weights to bias the score:

```php
$sigmie->newSearch('characters')
    ->properties($props)
    ->queryString('Mickey', weight: 2)
    ->queryString('Goofy', weight: 1)
    ->get();
```

## Limit which fields are searched

By default, every searchable field in your properties is queried. Narrow to specific fields with `fields()`:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Snow White')
    ->fields(['name'])                              // only search `name`
    ->get();
```

## Limit which fields are returned

Reduce response size by selecting only the fields you need:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Snow White')
    ->retrieve(['name', 'description'])
    ->get();
```

## Filter

The [filter parser](filter-parser.md) reads filters in a human-friendly syntax:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Sleeping Beauty')
    ->filters('stock>0 AND is:active AND NOT category:"Drama"')
    ->get();
```

Filters narrow the result set but don't affect relevance scoring.

### Hard filter clauses with `filterQuery()`

`filters()` parses a user-facing string. When a filter must **not** come from user input — a tenant scope, an ownership check, an access boundary — pass a pre-built query to `filterQuery()` instead:

```php
use Sigmie\Query\Queries\Term\Term;

$sigmie->newSearch('records')
    ->properties($props)
    ->queryString('checkup')
    ->filters("category:'public' OR category:'private'")   // user input, parsed
    ->filterQuery(new Term('tenant_id', $tenantId))         // hard clause, never parsed
    ->get();
```

The hard clause is ANDed with the parsed filter. Because it never passes through the parser, a malformed filter string can't drop it, and it doesn't alter the parsed query's own `OR`/`NOT` logic — that stays nested as a single clause. It applies everywhere the filter does: results, facet counts, and semantic (vector) pre-filtering. Call it more than once to add several clauses.

`filterQuery()` accepts any query clause, so you're not limited to the parser's syntax:

```php
use Sigmie\Query\Queries\Term\Terms;

->filterQuery(new Terms('owner_id', $allowedOwnerIds))
```

### Strict vs lenient parsing

By default `newSearch()` is **lenient**: an invalid filter clause is dropped, the search still runs, and the reason is collected under the response's `errors` key:

```php
$response = $sigmie->newSearch('products')
    ->properties($props)
    ->filters('nonexistent:"x"')
    ->get();

$response->json('errors');   // [['message' => 'Field nonexistent does not exist.', ...]]
```

To fail loudly instead — so a bad filter raises a `ParseException` rather than silently returning a broader result set — opt in with `throwOnError`:

```php
->filters('nonexistent:"x"', throwOnError: true)     // throws ParseException
```

The same flag is available on [`facets()`](facets.md) for the facet filter string.

## Sort

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Snow White')
    ->sort('_score:desc name:asc')
    ->get();
```

`_score:desc` is the default. `_score:asc` is not allowed — Elasticsearch can't sort relevance ascending. See [Sort Parser](sort-parser.md) for full syntax.

## Typo tolerance

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Sleping Buety')                  // typos OK
    ->typoTolerance()
    ->get();
```

The default policy: one typo allowed for terms 3+ characters long, two typos for 6+. Override the thresholds:

```php
->typoTolerance(oneTypoChars: 4, twoTypoChars: 8)
```

Restrict typos to specific fields:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Sleping Buety')
    ->typoTolerance()
    ->typoTolerantAttributes(['name'])
    ->get();
```

## Highlight matches

Wrap matching tokens in HTML for direct display:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('sleeping beauty')
    ->highlighting(
        ['name'],
        prefix: '<mark>',
        suffix: '</mark>',
    )
    ->get();
```

Default prefix/suffix is `<em>` / `</em>`.

## Weight fields

Give certain fields more influence on relevance:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('sleeping beauty')
    ->weight(['name' => 4, 'description' => 1])
    ->get();
```

A match in `name` now scores 4× higher than the same match in `description`.

## Minimum score

Drop low-relevance results:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Mickey')
    ->weight(['name' => 5])
    ->minScore(2)
    ->get();
```

## Paginate

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('sleeping beauty')
    ->from(10)
    ->size(10)
    ->get();
```

`from(10)->size(10)` returns the second page (skip first 10, take next 10).

`page()` is a shortcut:

```php
->page(2, 20)               // page 2, 20 per page (== from(20)->size(20))
```

## Deduplicate

Return one hit per value of a field. Useful for product variants:

```php
$sigmie->newSearch('products')
    ->properties($props)
    ->queryString('sneakers')
    ->uniqueBy('product_id')
    ->get();
```

Include the next best matches from each group as inner hits:

```php
->uniqueBy('product_id', top: 3)
```

The collapse field must be single-valued (e.g. `keyword`).

## Facets

Build sidebar filters with one method. See [Facets](facets.md):

```php
$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->facets('brand category price:100')
    ->get();

$facets = $response->json('facets');
```

## Semantic search

Enable vector matching alongside keyword search:

```php
$sigmie->newSearch('articles')
    ->properties($props)
    ->semantic()
    ->queryString('artificial intelligence')
    ->get();
```

Use vectors only (no keyword matching):

```php
->semantic()->disableKeywordSearch()
```

See [Semantic Search](semantic-search.md) for embeddings setup and accuracy levels.

## Autocomplete

```php
$response = $sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->autocompletePrefix('m')
    ->fields(['name'])
    ->retrieve(['name'])
    ->get();

$suggestions = $response->json('autocomplete');
```

## Multi-language

Search across multiple indices:

```php
$result = $sigmie->newSearch("$germanIndex,$englishIndex")
    ->properties($props)
    ->queryString('door tür')
    ->get();
```

## Nested fields

Search and retrieve nested fields with dot notation:

```php
$sigmie->newSearch('users')
    ->properties($props)
    ->queryString('Pluto')
    ->fields(['contact.dog.name'])
    ->retrieve(['contact.dog.name'])
    ->get();
```

## Reading results

```php
$response = $sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('mickey')
    ->get();

$response->total();                  // total matching documents
$response->hits();                   // array of hits
$response->json('hits');             // raw hits array
$response->json('hits.0._source');   // a specific value via dot notation
```

## Empty query strings

By default, an empty query string returns every document. To return nothing instead:

```php
->noResultsOnEmptySearch()
```

## Async execution

`promise()` returns a Guzzle promise instead of executing immediately:

```php
$promise = $sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('mickey')
    ->promise();
```

## Iterating over all matching hits

`size()` is for UIs. For exports, migrations, or bulk re-processing, use `each()` or `lazy()` to stream every matching document. Both reuse your filters, query string, and field scoping, and page internally using Point-in-Time + `search_after` — so concurrent writes don't break the cursor.

### With a callback

```php
use Sigmie\Document\Hit;

$sigmie->newSearch('orders')
    ->properties($props)
    ->filters('status:completed')
    ->each(function (Hit $hit) use ($csv): void {
        $csv->writeRow($hit->_source);
    });
```

Each `Hit` exposes `_id`, `_source`, and `_score`.

### With a generator

```php
$generator = $sigmie->newSearch('orders')
    ->properties($props)
    ->filters('status:completed')
    ->lazy();

foreach ($generator as $hit) {
    processHit($hit);
}
```

### Page size

Default 500 per page. Tune for memory vs. round-trips:

```php
$sigmie->newSearch('products')
    ->properties($props)
    ->chunk(100)
    ->each(function (Hit $hit): void {
        // 100 at a time
    });
```

### Sort during iteration

Point-in-Time needs a deterministic sort. Sigmie handles this for you:

- **`NewSearch::sort()`** — your sort string is kept. Sigmie appends a stable tiebreaker (`_shard_doc` on Elasticsearch, `_id` on OpenSearch) if you didn't already provide one. `_score`-only or `_doc`-only sorts are replaced by the tiebreaker.
- **`NewQuery::sortString()` / `sort(array)`** — call before the query method (`matchAll`, `bool`, etc.). Omit sort entirely to stream in stable but unranked order. Use field names that exist in your mapping (often a `.keyword` sub-field for text).
- **`raw()`** — include a top-level `sort` key in the body you pass.

```php
$multi->raw('orders', [
    'query' => ['match_all' => (object) []],
    'sort' => [['processed_at' => 'asc']],
]);
```

When the body includes `collapse`, Sigmie does not append the tiebreaker — Elasticsearch only allows one sort key with `collapse` + `search_after`, and that's your responsibility.

### Multi-search

`newMultiSearch()` registers multiple queries; a single `_msearch` returns one page each. To stream **all** matching hits across registered queries, call `each()` or `lazy()` on the multi-search:

```php
use Sigmie\Document\Hit;

$multi = $sigmie->newMultiSearch();

$multi->newSearch('orders')
    ->properties($orderProps)
    ->filters('status:pending')
    ->chunk(200);

$multi->newQuery('products')->matchAll();

$multi->raw('orders', [
    'query' => ['term' => ['status' => 'pending']],
])->chunk(200);

foreach ($multi->lazy() as $hit) {
    exportRow($hit);
}
```

Each registered search runs its own PIT iteration; results yield in registration order. Set `chunk()` per query — the multi-search has no global chunk size.

> **Note:** `each()` and `lazy()` ignore `from()`, `size()`, `page()`, and `highlighting()` — these are pagination/display concerns. Sort is honored as described above.
