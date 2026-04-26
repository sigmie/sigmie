---
title: Sort Parser
short_description: Parse sort expressions for search result ordering
keywords: [sort parser, sorting, order, search results]
category: Utilities
order: 2
related_pages: [search, query, filter-parser]
---

# Sort parser

## Introduction

With `NewSearch` or with `NewQuery` (call `sortString` before the query):

```php
$sigmie->newSearch('movies')->properties($properties)->sort('_score name:asc');
```

```php
$sigmie->newQuery('movies')->properties($properties)->sortString('_score name:asc');
```

Using the parser directly:

```php
$parser->parse('_score name:asc');
```

```bash
_score:desc rating:desc name:asc
```

```json
[
    {
        "_score": "desc"
    },
    {
        "rating": "desc"
    },
    {
        "name": "asc"
    }
]
```

**Note**: `_score` can be used alone (defaults to descending) or with `:desc` explicitly. `_score:asc` is not allowed.

### Properties

```php
$mappings = new Properties();

$parser = new SortParser($props);

$parser->parse('_score rating:desc name:asc');
```

```json
[
    "_score",
    {
        "rating": "desc"
    },
    {
        "name": "asc", // [tl! remove]
        "name.keyword": "asc" // [tl! add]
    }
]
```

```php
$blueprint = new Blueprint;
$blueprint->bool('active');
$blueprint->text('name')->keyword();
$blueprint->text('category');

$props = $blueprint();
```
