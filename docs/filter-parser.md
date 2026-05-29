---
title: Filter Parser
short_description: Filter Elasticsearch results with human-readable expressions — ranges, wildcards, AND/OR/NOT, nested fields, geo proximity. Full syntax reference.
keywords: [filter parser, filters, query parser, boolean queries, syntax]
category: Utilities
order: 1
related_pages: [search, sort-parser, facets]
---

# Filter Parser

The Filter Parser turns human-readable expressions into Elasticsearch boolean queries. You write `active:true AND price:100..500`; Sigmie compiles it into the right combination of `term`, `range`, and `bool` queries.

```php
use Sigmie\Parse\FilterParser;
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->keyword('category');
$props->bool('active');
$props->number('stock');

$parser = new FilterParser($props());
$query = $parser->parse('active:true AND category:"sports"');
```

In `newSearch()` and `newQuery()`, you almost never instantiate the parser directly — you pass a filter string to `filters()` or `parse()`:

```php
$sigmie->newSearch('products')
    ->properties($props)
    ->filters('active:true AND stock>0 AND price:100..500')
    ->queryString('laptop')
    ->get();
```

## Why use the parser

- **Reads like a sentence** — `active:true AND price:100..500` instead of nested `bool` arrays.
- **Type-aware** — validated against your property definitions.
- **Errors early** — invalid syntax raises a `ParseException` before hitting Elasticsearch.

## Exact match

```
category:"sports"
color:'red'
name:'John Doe'
name:"John Doe"
```

Single and double quotes are interchangeable.

### Numbers

Numbers don't need quotes:

```
price:100
stock:50
```

### Booleans

```
active:true
published:false
```

Boolean values are lowercase, no quotes.

### Field exists

```
email:*               # has any value
NOT email:*           # has no value
```

## Multiple values (IN)

Match any value from an array:

```
category:["sports", "action", "horror"]
status:["active", "pending"]
```

Whitespace inside an array is trimmed. Empty arrays match nothing.

## Wildcards

```
phone:'*650'         # ends with 650
phone:'2353*'        # starts with 2353
title:'*manager*'    # contains "manager"
```

## Ranges

### Comparison operators

```
price>=100
price<=200
stock>0
created_at>="2023-05-01"
```

Operators: `>`, `<`, `>=`, `<=`.

### Inclusive range

```
price:100..500
created_at:"2023-01-01".."2023-12-31"
```

`price:100..500` is equivalent to `price>=100 AND price<=500`.

## Logical operators

```
active:true AND category:"sports" AND stock>0
category:"action" OR category:"horror"
NOT category:"sports"
active:true AND NOT stock:0
```

Group with parentheses:

```
active:true AND (category:"action" OR category:"horror") AND stock>0
```

> **Note:** Multiple clauses without an operator throw `ParseException`:
>
> ```
> color:'red' size:'large'             # error
> color:'red' AND size:'large'         # OK
> ```

## Object properties

For flattened object fields, use dot notation:

```
contact.active:true
contact.name:"John Doe"
user.profile.settings.notifications:true
```

```php
$props->object('contact', function (NewProperties $p) {
    $p->bool('active');
    $p->name('name');
    $p->email('email');
});

$parser->parse('contact.active:true AND contact.name:"Alice"');
```

## Nested fields

For arrays of objects (mapped as `nested()`), use curly braces. All conditions inside the braces must match the **same** array element:

```
contact:{ active:true }
contact:{ name:"John Doe" AND verified:true }
```

```php
$props->nested('vehicles', function (NewProperties $p) {
    $p->keyword('make');
    $p->keyword('model');
});

$parser->parse("vehicles:{ make:'Powell Motors' AND model:'Canyonero' }");
```

This finds documents with a vehicle whose `make` is "Powell Motors" **and** model is "Canyonero" — not documents with one vehicle named "Powell Motors" and a separate vehicle modeled "Canyonero".

### Deep nesting

```
contact:{ address:{ city:"Berlin" AND marker:"X" } }
```

### Object vs nested syntax

Same data, different mappings — different syntax:

| Mapping | Syntax |
|---------|--------|
| `object()` | `contact.active:true AND contact.city:"Berlin"` |
| `nested()` | `contact:{ active:true AND city:"Berlin" }` |

`nested` preserves the relationship; `object` flattens everything to root.

## Geo-location

Filter by proximity to a point:

```
location:1km[51.49,13.77]
location:5mi[40.7128,-74.0060]
contact:{ location:1km[51.16,13.49] AND active:true }
```

The format is `field:distance[lat,lon]`. Supported units: `km`, `mi`, `m`, `yd`, `ft`, `nmi`, `cm`, `in`.

> **Note:** Zero distance returns nothing, even on an exact coordinate match. Use a small positive distance:
>
> ```
> location:0km[51.16,13.49]       # returns nothing
> location:1m[51.16,13.49]        # OK
> ```

## Empty values

```
database:""
tags:[]
```

Empty arrays match no documents.

## Escaping and special characters

### Quotes inside strings

```
description:"She said \"Hello World\""
title:'It\'s working'
```

### Dashes, spaces, parentheses

Values with spaces, dashes, or special characters need quotes:

```
status:'in-progress'
category:"crime & drama"
job_title:"Chief Executive Officer (CEO)"
industry:["Renewables & Environment"]
```

## Error handling

Invalid syntax raises `ParseException`:

```php
use Sigmie\Parse\ParseException;

try {
    $query = $parser->parse('color:"red" color:"blue"');     // missing operator
} catch (ParseException $e) {
    // log, surface to the user, etc.
}
```

Common causes:

- Missing logical operator between clauses.
- Referencing a field that isn't in your mappings.
- Mismatched parentheses or brackets.
- Excessive nesting (the parser has a depth limit).

### Field validation

The parser validates field names against your property definitions:

```php
$props = new NewProperties;
$props->keyword('category');

$parser = new FilterParser($props());

$parser->parse('category:"sports"');                  // OK
$parser->parse('subject_service:{ id:"23" }');        // error — field unknown

if (!empty($parser->errors())) {
    // handle errors
}
```

### Throwing vs collecting

Whether a bad filter throws or is collected depends on how the parser is invoked:

- **Directly** (`new FilterParser(...)`) or via `newQuery()` — throws `ParseException` on the first error.
- **Via `newSearch()`** — lenient by default: the bad clause is dropped, the search runs, and the error is collected under the response's `errors` key.

Opt into throwing on the search builder with `throwOnError`:

```php
$sigmie->newSearch('products')
    ->properties($props)
    ->filters('nonexistent:"x"', throwOnError: true);   // throws instead of collecting
```

When a silently-dropped clause would be unsafe — returning a broader result set than intended — pass `throwOnError: true` and treat a non-empty `errors` array as a failed request rather than a warning.

## Syntax cheatsheet

| Operation | Syntax | Example |
|-----------|--------|---------|
| Exact match | `field:"value"` | `category:"sports"` |
| Number | `field:123` | `price:100` |
| Boolean | `field:true` | `active:true` |
| Field exists | `field:*` | `email:*` |
| IN | `field:[v1,v2]` | `status:["active","pending"]` |
| Wildcard | `field:'*pat*'` | `phone:'*650'` |
| Range | `field:min..max` | `price:100..500` |
| Greater than | `field>value` | `stock>0` |
| Less than | `field<value` | `price<100` |
| AND | `a AND b` | `active:true AND stock>0` |
| OR | `a OR b` | `cat:"a" OR cat:"b"` |
| NOT | `NOT a` | `NOT category:"books"` |
| Object | `obj.field:value` | `contact.active:true` |
| Nested | `field:{condition}` | `contact:{active:true}` |
| Geo | `field:dist[lat,lon]` | `location:1km[51,13]` |
