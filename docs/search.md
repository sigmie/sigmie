---
title: Search
short_description: Build user-facing Elasticsearch searches with Sigmie — typo tolerance, faceted navigation, highlighting, semantic search, and filter-parser syntax.
keywords: [search, query, filters, sorting, highlighting, typo tolerance, nested, inner hits]
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

Use `except()` to drop fields from every hit. It wins over `retrieve()`, so a nested path stays hidden even when its parent is retrieved:

```php
$sigmie->newSearch('cases')
    ->properties($props)
    ->queryString('appeal')
    ->except(['participants.identification_number']) // [tl! highlight]
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

Without `fields()`, the query searches every field, including the fields inside nested fields.

## Matching nested items (inner hits)

A nested field holds a list of objects, such as the reviews of a product. When a search for "battery" matches one review, Elasticsearch returns the whole product. Its `_source` holds every review, so you cannot see which review matched. Inner hits answer that question. They return, per hit, the nested items that matched:

```
product "Aurora Phone"           search "battery"
  reviews[0] Battery easily...   -> matched
  reviews[1] Camera is great...  -> not matched
  reviews[2] Battery drains...   -> matched

_source          -> the product, all 3 reviews
_matches.reviews -> reviews 0 and 2, total 2
```

Take a shop index with nested reviews:

```php
$props = new NewProperties;
$props->title('title');
$props->nested('reviews', function (NewProperties $props) {
    $props->text('comment');
    $props->number('stars');
    $props->keyword('author');
});
$props->nested('questions', function (NewProperties $props) {
    $props->text('text');
});
$props->nested('answers', function (NewProperties $props) {
    $props->text('text');
});

$sigmie->newIndex('products')->properties($props)->lowercase()->create();

$sigmie->collect('products', refresh: true)->merge([
    new Document([
        'title' => 'Aurora Phone',
        'reviews' => [
            ['comment' => 'Battery easily lasts two days.', 'stars' => 5, 'author' => 'anna'],
            ['comment' => 'Camera is great in daylight.', 'stars' => 4, 'author' => 'ben'],
            ['comment' => 'Battery drains fast when gaming.', 'stars' => 2, 'author' => 'chris'],
        ],
        'questions' => [
            ['text' => 'Is the battery replaceable?'],
            ['text' => 'Does it support eSIM?'],
        ],
        'answers' => [
            ['text' => 'No, the battery is sealed.'],
            ['text' => 'Yes, eSIM works.'],
        ],
    ], 'aurora'),
]);
```

Pass the nested path whose matching items you want to `innerHits()`:

```php
$hits = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('battery')
    ->innerHits('reviews') // [tl! highlight]
    ->get()
    ->json('hits');
```

Each hit keeps its `_source` and gets a `_matches` key:

```json
{
    "_id": "aurora",
    "_source": {
        "title": "Aurora Phone",
        "reviews": [
            {"comment": "Battery easily lasts two days.", "stars": 5, "author": "anna"},
            {"comment": "Camera is great in daylight.", "stars": 4, "author": "ben"},
            {"comment": "Battery drains fast when gaming.", "stars": 2, "author": "chris"}
        ],
        "questions": [
            {"text": "Is the battery replaceable?"},
            {"text": "Does it support eSIM?"}
        ],
        "answers": [
            {"text": "No, the battery is sealed."},
            {"text": "Yes, eSIM works."}
        ]
    },
    "_matches": {
        "reviews": {
            "total": 2,
            "items": [
                {"_offset": 0, "comment": "Battery easily lasts two days.", "stars": 5, "author": "anna"},
                {"_offset": 2, "comment": "Battery drains fast when gaming.", "stars": 2, "author": "chris"}
            ]
        }
    }
}
```

| Key | Holds |
|-----|-------|
| `_source` | The document, or the `retrieve()` fields. Inner hits never change it. |
| `_matches.reviews.total` | How many reviews of this product matched. |
| `_matches.reviews.items` | The matching reviews, best match first. |
| `_offset` | The item's position in the `reviews` array, starting at 0. |

Only the paths you name get matches. Without `innerHits()`, a hit has no `_matches` key. A requested path with no matching items still appears, with `total: 0` and no items.

### Reading matches

Each `Hit` from `hits()` reads its matches by nested path:

```php
$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('battery')
    ->innerHits('reviews.comment')
    ->innerHits('questions.text')
    ->innerHits('answers')
    ->get();

foreach ($response->hits() as $hit) {
    $hit->matches('reviews');      // [['_offset' => 0, 'comment' => 'Battery easily lasts two days.'],
                                   //  ['_offset' => 2, 'comment' => 'Battery drains fast when gaming.']]
    $hit->matchesTotal('reviews'); // 2
    $hit->matches();               // ['reviews' => [...], 'questions' => [...], 'answers' => [...]]
}
```

| Method | Returns |
|--------|---------|
| `matches(string $path)` | The matching items of that nested path. |
| `matchesTotal(string $path)` | How many items of that path matched, including items beyond `size`. |
| `matches()` | The items of every requested path, keyed by path. |

The argument is the nested path, the same key as in `_matches`. A field inside it resolves to that path, so `matches('questions.text')` returns the `questions` items. A path you did not request returns `[]`, and its total is `0`. `Hit::toArray()` includes `_matches` only when the search requested inner hits. Reranked hits keep their matches.

The raw form is the `_matches` key of `json('hits')`:

```php
$response->json('hits.0._matches.reviews')['total']; // 2
```

### Select fields with dot syntax

Name a field inside the nested field, instead of the nested field itself, to return only that field of each matching item. Call again for another field of the same path, and the fields merge:

```php
->innerHits('reviews.comment')
->innerHits('reviews.stars')
```

```json
"_matches": {
    "reviews": {
        "total": 2,
        "items": [
            {"_offset": 0, "comment": "Battery easily lasts two days.", "stars": 5},
            {"_offset": 2, "comment": "Battery drains fast when gaming.", "stars": 2}
        ]
    }
}
```

### Only the matching items

`retrieve()` controls `_source`, and `innerHits()` controls `_matches`. Leave the nested field out of `retrieve()` to get the product title plus only the matching reviews, instead of every review:

```php
$hits = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('battery')
    ->retrieve(['title']) // [tl! highlight]
    ->innerHits('reviews.comment')
    ->get()
    ->json('hits');
```

```json
{
    "_id": "aurora",
    "_source": {"title": "Aurora Phone"},
    "_matches": {
        "reviews": {
            "total": 2,
            "items": [
                {"_offset": 0, "comment": "Battery easily lasts two days."},
                {"_offset": 2, "comment": "Battery drains fast when gaming."}
            ]
        }
    }
}
```

### Several paths, each with its own size

Call `innerHits()` once per nested path. Each path gets its own entry and its own `size`:

```php
$hits = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('battery')
    ->retrieve(['title'])
    ->innerHits('reviews', size: 10)
    ->innerHits('questions.text', size: 5)
    ->innerHits('answers')                 // default size: 100
    ->get()
    ->json('hits');
```

```json
"_matches": {
    "reviews": {
        "total": 2,
        "items": [
            {"_offset": 0, "comment": "Battery easily lasts two days.", "stars": 5, "author": "anna"},
            {"_offset": 2, "comment": "Battery drains fast when gaming.", "stars": 2, "author": "chris"}
        ]
    },
    "questions": {
        "total": 1,
        "items": [
            {"_offset": 0, "text": "Is the battery replaceable?"}
        ]
    },
    "answers": {
        "total": 1,
        "items": [
            {"_offset": 0, "text": "No, the battery is sealed."}
        ]
    }
}
```

An array is the shortcut for several fields or paths that share one size:

```php
->innerHits(['reviews.comment', 'reviews.stars', 'questions.text'], size: 5)
```

### Size and totals

`innerHits()` returns every matching item, up to 100 per path. 100 is the Elasticsearch default for `index.max_inner_result_window`. Pass `size` to return fewer:

```php
->retrieve(['title'])
->innerHits('reviews.comment', size: 1) // [tl! highlight]
```

`total` still counts every match, so a capped list is visible. Here `total` is 2 and one item returns:

```json
"_matches": {
    "reviews": {
        "total": 2,
        "items": [
            {"_offset": 0, "comment": "Battery easily lasts two days."}
        ]
    }
}
```

When `total` is greater than the number of items, the list is capped. The same applies when more than 100 items match: `items` holds 100 and `total` holds the real count. To return more than 100, raise `index.max_inner_result_window` on the index and pass a larger `size`.

When you call `innerHits()` again for the same path, the later `size` wins and the fields merge. A whole-path request covers every field under it, so `->innerHits('reviews.comment', size: 8)->innerHits('reviews', size: 1)` returns one whole review.

### Nested filters

A [nested filter](filter-parser.md) also produces matches. When the query and a filter both match items of one path, `_matches` holds each item once:

```php
->queryString('battery')                // matches reviews 0 and 2
->filters('reviews:{stars>=4}')          // matches reviews 0 and 1
->innerHits('reviews.comment')           // reviews 0, 1 and 2, total 3
```

Deeper nested paths use dots too. For orders with nested items, `innerHits('orders.items.sku')` with the filter `orders:{items:{sku:'y'}}` returns the matching items under `_matches['orders.items']`. Each item also holds `_parents`, the offsets of its parent items, such as `{"orders": 1}`.

`except()` applies to the items too. With `->except(['reviews.author'])`, no item holds `author`, even with `innerHits('reviews')`.

> **Note:** `innerHits()` throws an `InvalidArgumentException` for a field that does not exist or is not inside a nested field, such as `title`.

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
