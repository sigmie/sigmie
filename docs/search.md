---
title: Search
short_description: Run user-facing Elasticsearch searches with Sigmie newSearch(), including filters, sorting, typo tolerance, highlighting, facets, and nested inner hits.
keywords: [search, query, filters, sorting, highlighting, typo tolerance, nested, inner hits]
category: Core Concepts
order: 5
related_pages: [query, document, semantic-search, filter-parser]
---

# Search

`newSearch()` builds the search a user runs from a search box. Each section on this page solves one task with it. For the boolean query DSL, use [Advanced Queries](query.md) instead.

Every search needs the index properties and a query string:

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

Sigmie reads the properties to decide how to query each field.

## Weight several query strings

Call `queryString()` once per string. The `weight` argument biases the score toward one string:

```php
$sigmie->newSearch('characters')
    ->properties($props)
    ->queryString('Mickey', weight: 2) // [tl! highlight]
    ->queryString('Goofy', weight: 1)
    ->get();
```

## Limit which fields are searched

The search queries every field in the properties. To query only some fields, pass them to `fields()`:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Snow White')
    ->fields(['name']) // [tl! highlight]
    ->get();
```

## Limit which fields are returned

To return only some fields of each hit, pass them to `retrieve()`:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Snow White')
    ->retrieve(['name', 'description']) // [tl! highlight]
    ->get();
```

To drop fields from every hit, pass them to `except()`. `except()` wins over `retrieve()`, so a nested path stays hidden even when you retrieve its parent:

```php
$sigmie->newSearch('customers')
    ->properties($props)
    ->queryString('jane')
    ->except(['contacts.email']) // [tl! highlight]
    ->get();
```

## Filter

Pass a [filter string](filter-parser.md) to `filters()`. Filters narrow the result set and do not change the score:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Sleeping Beauty')
    ->filters('stock>0 AND active:true AND NOT category:"Drama"') // [tl! highlight]
    ->get();
```

### Add a filter the user cannot change

A tenant scope, an ownership check, or an access rule must not come from user input. Pass these as a query object to `filterQuery()`:

```php
use Sigmie\Query\Queries\Term\Term;

$sigmie->newSearch('records')
    ->properties($props)
    ->queryString('checkup')
    ->filters("category:'public' OR category:'private'")   // user input, parsed
    ->filterQuery(new Term('tenant_id', $tenantId))         // never parsed [tl! highlight]
    ->get();
```

Sigmie combines the `filterQuery()` clause and the parsed filter with AND. The parsed filter stays one clause, so its own `OR` and `NOT` logic does not change. A malformed filter string cannot drop the `filterQuery()` clause, because the parser never reads it. The clause applies to the results, the facet counts, and the semantic (vector) pre-filter.

`filterQuery()` accepts any query clause. Call it once per clause:

```php
use Sigmie\Query\Queries\Term\Terms;

->filterQuery(new Terms('owner_id', $allowedOwnerIds))
```

### Choose strict or lenient parsing

By default, `filters()` is lenient. Sigmie replaces a clause that names an unknown field with a clause that matches nothing, runs the search, and puts the reason under the response's `errors` key:

```php
$response = $sigmie->newSearch('products')
    ->properties($props)
    ->filters('nonexistent:"x"')
    ->get();

$response->json('errors');   // [['message' => 'Field nonexistent does not exist.', ...]]
```

The replaced clause changes the result. After `AND`, it returns no hits. After `NOT`, it removes no hits, so the search returns more than the filter allows. To throw a `ParseException` instead, pass `throwOnError: true`:

```php
->filters('nonexistent:"x"', throwOnError: true)     // throws ParseException
```

[`facets()`](facets.md) takes the same flag for its filter string. Syntax errors, such as a missing operator or an unclosed parenthesis, throw in both modes. See [Filter Parser](filter-parser.md#errors) for the full list.

## Sort

Pass a [sort string](sort-parser.md) to `sort()`:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Snow White')
    ->sort('_score:desc name:asc') // [tl! highlight]
    ->get();
```

The default sort is `_score`. Unlike `filters()`, `sort()` throws a `ParseException` on an invalid sort string. With `throwOnError: false`, Sigmie skips the invalid clauses.

## Tolerate typos

List the fields that accept typos in `typoTolerantAttributes()`:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Sleping Buety')
    ->typoTolerantAttributes(['name']) // [tl! highlight]
    ->get();
```

Terms with 3 or more characters accept one typo. Terms with 6 or more characters accept two. To change these limits, call `typoTolerance()`:

```php
->typoTolerance(oneTypoChars: 4, twoTypoChars: 8)
```

> **Note:** `typoTolerance()` only sets the limits. Fields that are not in `typoTolerantAttributes()` always match exactly.

## Highlight matches

Pass the fields and the HTML that wraps each match to `highlighting()`:

```php
$response = $sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('sleeping beauty')
    ->highlighting(
        ['name'],
        prefix: '<mark>',
        suffix: '</mark>',
    )
    ->get();

$response->json('hits.0.highlight.name');   // fragments with each match in <mark>
```

`prefix` and `suffix` have no default. Each highlighted field returns up to three fragments of 150 characters.

## Weight fields

Pass a weight per field to `weight()`:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('sleeping beauty')
    ->weight(['name' => 4, 'description' => 1]) // [tl! highlight]
    ->get();
```

A match in `name` now scores 4 times higher than the same match in `description`.

## Drop low-scoring hits

Pass the lowest score to keep to `minScore()`:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('Mickey')
    ->weight(['name' => 5])
    ->minScore(2) // [tl! highlight]
    ->get();
```

## Paginate

`size()` sets the hits per page and defaults to 20. `from()` sets how many hits to skip. This search returns the second page of 10:

```php
$sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('sleeping beauty')
    ->from(10)
    ->size(10)
    ->get();
```

`page()` takes a page number and a page size instead:

```php
->page(2, 20)               // same as from(20)->size(20)
```

## Return one hit per group

To return one hit per value of a field, such as one hit per product across its variants, pass the field to `uniqueBy()`:

```php
$sigmie->newSearch('products')
    ->properties($props)
    ->queryString('sneakers')
    ->uniqueBy('product_id') // [tl! highlight]
    ->get();
```

To also return the next best hits of each group as inner hits, pass `top`:

```php
->uniqueBy('product_id', top: 3)
```

The field must hold one value per document, such as a `keyword`.

## Add facets

Pass the facet fields to `facets()`. See [Facets](facets.md) for the syntax:

```php
$response = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->facets('brand category price:100') // [tl! highlight]
    ->get();

$facets = $response->json('facets');
```

## Add semantic search

Call `semantic()` to match by vector as well as by keyword:

```php
$sigmie->newSearch('articles')
    ->properties($props)
    ->semantic() // [tl! highlight]
    ->queryString('artificial intelligence')
    ->get();
```

To match by vector only, also call `disableKeywordSearch()`:

```php
->semantic()->disableKeywordSearch()
```

See [Semantic Search](semantic-search.md) to set up embeddings.

## Suggest completions

Autocomplete reads the first `completion` field in the properties. Pass the typed prefix to `autocompletePrefix()`:

```php
$props = new NewProperties;
$props->completion('title');

$response = $sigmie->newSearch('movies')
    ->properties($props)
    ->autocompletePrefix('star') // [tl! highlight]
    ->autocompleteSize(3)
    ->queryString('')
    ->get();

$response->json('autocomplete.0.options');   // [['text' => 'Star Trek', ...], ...]
```

`autocompleteSize()` defaults to 5.

## Search several indices

Pass the index names to `newSearch()`, separated by commas:

```php
$result = $sigmie->newSearch("$germanIndex,$englishIndex")
    ->properties($props)
    ->queryString('door tür')
    ->get();
```

## Search nested fields

Name a field inside a nested or object field with dot notation:

```php
$sigmie->newSearch('users')
    ->properties($props)
    ->queryString('Pluto')
    ->fields(['contact.dog.name'])
    ->retrieve(['contact.dog.name'])
    ->get();
```

Without `fields()`, the search also queries every field inside nested fields.

## Matching nested items (inner hits)

A nested field holds a list of objects, such as the reviews of a product. When a search for "battery" matches one review, Elasticsearch returns the whole product. Its `_source` holds every review, so you cannot see which review matched. Inner hits return, per hit, the nested items that matched:

```
product "Aurora Phone"           search "battery"
  reviews[0] Battery easily...   -> matched
  reviews[1] Camera is great...  -> not matched
  reviews[2] Battery drains...   -> matched

_source          -> the product, all 3 reviews
_matches.reviews -> reviews 0 and 2, total 2
```

The examples below use a shop index with nested reviews, questions, and answers:

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

Pass the nested path to `innerHits()`:

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

### Read matches from a hit

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

The argument is the nested path, the same key as in `_matches`. A field inside the path resolves to the path, so `matches('questions.text')` returns the `questions` items. A path you did not request returns `[]`, and its total is `0`. `Hit::toArray()` includes `_matches` only when the search requested inner hits. Reranked hits keep their matches.

The raw form is the `_matches` key of `json('hits')`:

```php
$response->json('hits.0._matches.reviews')['total']; // 2
```

### Return only some fields of each item

Name a field inside the nested field to return only that field of each matching item. Call `innerHits()` again for another field of the same path, and the fields merge:

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

### Return only the matching items

`retrieve()` controls `_source`, and `innerHits()` controls `_matches`. To get the product title and only the matching reviews, leave the nested field out of `retrieve()`:

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

### Request several paths

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

To give several fields or paths one size, pass an array:

```php
->innerHits(['reviews.comment', 'reviews.stars', 'questions.text'], size: 5)
```

### Cap the number of items

`innerHits()` returns up to 100 matching items per path. 100 is the Elasticsearch default for `index.max_inner_result_window`. To return fewer, pass `size`:

```php
->retrieve(['title'])
->innerHits('reviews.comment', size: 1) // [tl! highlight]
```

`total` still counts every match. Here `total` is 2 and one item returns:

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

When `total` is greater than the number of items, the list is capped. With more than 100 matches, `items` holds 100 and `total` holds the real count. To return more than 100, raise `index.max_inner_result_window` on the index and pass a larger `size`.

When you call `innerHits()` again for the same path, the later `size` wins and the fields merge. A whole-path request covers every field under it, so `->innerHits('reviews.comment', size: 8)->innerHits('reviews', size: 1)` returns one whole review.

### Combine with nested filters

A [nested filter](filter-parser.md#nested-field-filtering) also produces matches. When the query and a filter both match items of one path, `_matches` holds each item once:

```php
->queryString('battery')                // matches reviews 0 and 2
->filters('reviews:{stars>=4}')          // matches reviews 0 and 1
->innerHits('reviews.comment')           // reviews 0, 1 and 2, total 3
```

Deeper nested paths use dots too. For orders with nested items, `innerHits('orders.items.sku')` with the filter `orders:{items:{sku:'y'}}` returns the matching items under `_matches['orders.items']`. Each item also holds `_parents`, the offsets of its parent items, such as `{"orders": 1}`.

`except()` applies to the items too. With `->except(['reviews.author'])`, no item holds `author`, even with `innerHits('reviews')`.

> **Note:** `innerHits()` throws an `InvalidArgumentException` for a field that does not exist or is not inside a nested field, such as `title`.

## Read the response

`get()` returns the response:

```php
$response = $sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('mickey')
    ->get();

$response->total();                  // total matching documents
$response->hits();                   // array of Hit objects
$response->json('hits');             // raw hits array
$response->json('hits.0._source');   // one value, by dot notation
```

## Return nothing for an empty query

An empty query string returns every document. To return no documents, call `noResultsOnEmptySearch()`:

```php
->noResultsOnEmptySearch()
```

## Run the search asynchronously

`promise()` returns a Guzzle promise and does not wait for the response:

```php
$promise = $sigmie->newSearch('fairy-tales')
    ->properties($props)
    ->queryString('mickey')
    ->promise();
```

## Iterating over all matching hits

`size()` sets one page. To read every matching document, for an export or a migration, call `each()` or `lazy()`. Both keep your query string, filters, and fields. They page with a point in time (PIT) and `search_after`, so writes during the iteration do not break it.

`each()` calls a function for each hit:

```php
use Sigmie\Document\Hit;

$sigmie->newSearch('orders')
    ->properties($props)
    ->filters('status:completed')
    ->each(function (Hit $hit) use ($csv): void {
        $csv->writeRow($hit->_source);
    });
```

`lazy()` returns a generator:

```php
$generator = $sigmie->newSearch('orders')
    ->properties($props)
    ->filters('status:completed')
    ->lazy();

foreach ($generator as $hit) {
    processHit($hit);
}
```

Each `Hit` has `_id`, `_source`, and `_score`.

> **Note:** `each()` and `lazy()` ignore `from()`, `size()`, `page()`, and `highlighting()`.

### Set the page size

Each request fetches 500 hits by default. To change it, call `chunk()`. A smaller chunk uses less memory and sends more requests:

```php
$sigmie->newSearch('products')
    ->properties($props)
    ->chunk(100)
    ->each(function (Hit $hit): void {
        // 100 at a time
    });
```

### Sort the iteration

A point in time needs a sort with a unique last key. Sigmie adds one, `_shard_doc` on Elasticsearch and `_id` on OpenSearch, unless your sort already ends with it.

| Builder | Where the sort comes from |
|---------|---------------------------|
| `newSearch()` | The `sort()` string. A sort on `_score` or `_doc` alone becomes the tiebreaker only. |
| `newQuery()` | `sortString()` or `sort()`. Without a sort, hits arrive in a stable but unranked order. |
| `raw()` | The top-level `sort` key of the body. |

For `newQuery()`, sort on fields that exist in the mapping:

```php
$multi->raw('orders', [
    'query' => ['match_all' => (object) []],
    'sort' => [['processed_at' => 'asc']],
]);
```

With `collapse` in the body, Sigmie adds no tiebreaker. Elasticsearch allows one sort key with `collapse` and `search_after`, so you choose that key.

### Iterate over a multi-search

`newMultiSearch()` sends several searches in one `_msearch` request, and `get()` returns one page of each. To read every hit of every search, call `each()` or `lazy()` on the multi-search:

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

Each search runs its own point in time, and hits arrive in the order you added the searches. Call `chunk()` on each search. The multi-search has no shared chunk size.
