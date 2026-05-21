---
title: Advanced Queries
short_description: Build raw Elasticsearch queries with boolean logic and DSL access
keywords: [query, advanced query, bool query, elasticsearch dsl, custom queries]
category: Core Concepts
order: 6
related_pages: [search, document, semantic-search, sort-parser]
---

# Advanced Queries

`newQuery()` gives you direct access to Elasticsearch's boolean query DSL. Reach for it when you need control [`newSearch()`](search.md) doesn't expose — custom scoring, nested boolean logic, or features specific to your Elasticsearch version.

```php
use Sigmie\Query\Queries\Compound\Boolean;

$response = $sigmie->newQuery('disney-movies')
    ->properties($props)
    ->sortString('name:asc')
    ->bool(function (Boolean $bool) {
        $bool->filter()->matchAll();
        $bool->filter()->multiMatch('goofy', ['name', 'description']);
        $bool->must()->term('is_active', true);
        $bool->mustNot()->term('is_active', false);
        $bool->mustNot()->wildcard('foo', '**/*');
        $bool->should()->bool(fn (Boolean $bool) =>
            $bool->must()->match('name', 'Mickey')
        );
    })
    ->from(0)
    ->size(15)
    ->get();
```

## When to use each builder

Use **[`newSearch()`](search.md)** for:

- User-facing search.
- Built-in features (typo tolerance, highlighting, facets, semantic search).
- Most ordinary search code.

Use **`newQuery()`** for:

- Complex boolean logic.
- Custom scoring (`scriptScore`, `functionScore`).
- Direct Elasticsearch DSL features Sigmie doesn't wrap.
- Migrating from raw Elasticsearch queries.

## Basic queries

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->name('name');
$props->number('age')->integer();

$response = $sigmie->newQuery('users')
    ->properties($props)
    ->matchAll()
    ->get();
```

Properties are required for complex queries — they let Sigmie parse field names and route queries correctly.

## Boolean queries

A boolean query has four clause types, each handling matches differently:

| Clause | Behavior | Affects score |
|--------|----------|---------------|
| `must()` | Must match | Yes |
| `mustNot()` | Must not match | No |
| `should()` | Should match (OR) | Yes |
| `filter()` | Must match | No |

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

### Must — AND

Every clause inside `must()` must match:

```php
$sigmie->newQuery('products')
    ->bool(function (Boolean $bool) {
        $bool->must()->term('is_active', true);
        $bool->must()->range('stock', ['>' => 0]);
    });
```

SQL equivalent:

```sql
SELECT * FROM products WHERE is_active = TRUE AND stock > 0;
```

### Must Not — NOT

Documents matching any `mustNot()` clause are excluded:

```php
$sigmie->newQuery('products')
    ->bool(function (Boolean $bool) {
        $bool->mustNot()->term('is_active', false);
        $bool->mustNot()->term('stock', 0);
    });
```

### Should — OR

At least one `should()` clause must match. Multiple clauses are OR'd:

```php
$sigmie->newQuery('movies')
    ->bool(function (Boolean $bool) {
        $bool->should()->term('category', 'fantasy');
        $bool->should()->term('category', 'musical');
    });
```

### Filter — AND without scoring

Same logic as `must()`, but doesn't influence `_score`. Filter queries are cached by Elasticsearch — use them whenever scoring doesn't matter:

```php
$sigmie->newQuery('movies')
    ->bool(function (Boolean $bool) {
        $bool->filter()->term('is_active', true);
    });
```

## Standalone queries

Outside a boolean wrapper, query types can be called directly on the builder:

```php
// Instead of wrapping in bool:
$sigmie->newQuery('movies')
    ->bool(function (Boolean $bool) {
        $bool->filter()->term('active', true);
    });

// Call directly:
$sigmie->newQuery('movies')->term('active', true);
```

## Query types

### Match all / match none

```php
$query->matchAll();
$query->matchNone();
```

### Term and terms

`term()` finds an exact value — best for `keyword`, `bool`, `integer`, etc:

```php
$query->term('active', true);
$query->term('user_id', 13);
```

`terms()` matches any of several values:

```php
$query->terms('category', ['horror', 'action']);
```

> **Note:** `term()` against an analyzed text field usually doesn't work — the field is tokenized. Add a `.keyword` sub-field if you need exact matching:
>
> ```php
> $props->text('category')->keyword();
> // then:
> $query->term('category.keyword', 'drama');
> ```

### Match

Analyzed query — best for text fields:

```php
$query->match('name', 'mickey');
```

### Multi-match

Match across multiple fields:

```php
$query->multiMatch(['name', 'username'], 'mickey');
```

### Range

Filter numeric and date ranges:

```php
$query->range('count', ['>=' => 233]);
$query->range('price', ['>=' => 30, '<=' => 130]);
```

Operators: `>=`, `>`, `<=`, `<`.

### Exists

Document has any value for the field:

```php
$query->exists('director');
```

### Ids

Match by document `_id`:

```php
$query->ids(['dkKwMe4UBAUb2dMteRe2', 'wd6Me4UBAUb2dMJT']);
```

### Regex, wildcard, prefix, fuzzy

```php
$query->regex('category', '(horror|action)');
$query->wildcard('name', 'john*');
$query->prefix('name', 'john');
$query->fuzzy('name', 'john');
```

## Parsing a filter string

For ad-hoc queries built from human input, `parse()` accepts the same syntax as [Filter Parser](filter-parser.md):

```php
$sigmie->newQuery('movies')
    ->properties($props)
    ->parse('name:"John Doe" AND age<21')
    ->get();
```

## Custom scoring

### Script score

Replace or multiply the score with a custom Painless script:

```php
$sigmie->newQuery('movies')
    ->properties($props)
    ->matchAll()
    ->scriptScore(
        source: "Math.log(2 + doc['popularity'].value)",
        boostMode: 'replace',
    )
    ->get();
```

### Function score

```php
$sigmie->newQuery('movies')
    ->properties($props)
    ->functionScore()
    ->get();
```

## Boosting

Boost a query's contribution to `_score`:

```php
$query->matchAll(boost: 5);
```

## Aggregations and facets

Add facets the same way as in `newSearch()`:

```php
$response = $sigmie->newQuery('products')
    ->properties($props)
    ->matchAll()
    ->facets('category')
    ->get();

$facets = $response->json('aggregations');
```

For raw aggregations, see [Aggregations](aggregations.md).

## Sorting

Call `sort()` or `sortString()` **before** the query method (`matchAll`, `bool`, `parse`, etc.). Each call replaces the previous sort — put all fields in a single string.

```php
$query->sortString('name:asc created_at:desc');
$query->sort([['year' => 'desc'], ['_score' => 'desc']]);
```

`_score:asc` is not allowed.

> **Note:** Sorting on text fields requires a `.keyword` sub-field. Add one with `$props->text('name')->keyword()->makeSortable()`.

See [Sort Parser](sort-parser.md) for full syntax.

## Pagination

`from` and `size` are on the `Search` instance returned after the query method:

```php
$response = $sigmie->newQuery('movies')
    ->properties($props)
    ->sortString('title:asc')
    ->matchAll()
    ->from(0)
    ->size(20)
    ->get();
```

## Reading responses

```php
$response = $sigmie->newQuery('movies')
    ->properties($props)
    ->matchAll()
    ->get();

$response->json();                       // full response
$response->json('hits.hits');            // hits array
$response->json('hits.total.value');     // total count
```

## Debugging

`getDSL()` returns the underlying Elasticsearch JSON:

```php
$dsl = $query->getDSL();
```

## Performance

- Use `filter()` instead of `must()` when scoring doesn't matter. Filter queries are cached.
- Prefer `term()` over `match()` for exact matches.
- Limit `size()` to what you need.
- Use `retrieve()` (on `newSearch()`) to drop unused fields from the response.

```php
$sigmie->newQuery('products')
    ->bool(function (Boolean $bool) {
        $bool->filter()->term('status', 'active');     // cached, no scoring
        $bool->must()->match('title', $searchTerm);    // scored
    })
    ->size(10)
    ->get();
```
