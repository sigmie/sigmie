---
title: Filter Parser
short_description: Sigmie filter syntax reference for Elasticsearch, covering exact values, ranges, wildcards, AND, OR, NOT, nested and object fields, and geo distance.
keywords: [filter parser, filters, query parser, boolean queries, syntax]
category: Utilities
order: 1
related_pages: [search, sort-parser, facets]
---

# Filter Parser

The filter parser turns a filter string such as `active:true AND price:100..500` into an Elasticsearch `bool` query. It checks every field name against your properties.

Pass a filter string to `filters()` on `newSearch()`, or to `parse()` on `newQuery()`:

```php
$sigmie->newSearch('products')
    ->properties($props)
    ->filters('active:true AND stock>0 AND price:100..500') // [tl! highlight]
    ->queryString('laptop')
    ->get();
```

To build the query without a search, create a `FilterParser`:

```php
use Sigmie\Mappings\NewProperties;
use Sigmie\Parse\FilterParser;

$props = new NewProperties;
$props->keyword('category');
$props->bool('active');
$props->number('stock');

$parser = new FilterParser($props());
$query = $parser->parse('active:true AND category:"sports"');
```

`parse()` returns a `Boolean` query. An empty string returns a query that matches every document.

## Exact values

```
category:"sports"
color:'red'
name:'John Doe'
status:active
```

Single and double quotes are interchangeable. A value without spaces or special characters needs no quotes.

### Numbers

```
price:100
stock:50
```

### Booleans

```
active:true
published:false
```

`true` and `false` are lowercase and have no quotes.

### Empty strings

```
database:""
```

`field:""` matches documents where the field holds an empty string.

## Field exists

```
email:*               # has any value
NOT email:*           # has no value
```

## Multiple values

A list matches documents with any of its values:

```
category:["sports", "action", "horror"]
status:["active", "pending"]
```

The parser trims spaces around each value. An empty list, `tags:[]`, matches no documents.

## Wildcards

```
phone:'*650'         # ends with 650
phone:'2353*'        # starts with 2353
title:'*manager*'    # contains "manager"
```

To match a literal `*`, escape it: `category:'A\*B'` matches the value `A*B`.

## Document IDs

```
_id:"dkKwMe4UBAUb2dMteRe2"
_id:["dkKwMe4UBAUb2dMteRe2", "wd6Me4UBAUb2dMJT"]
```

`_id` needs no entry in the properties.

## Ranges

### Comparison operators

```
price>=100
price<=200
stock>0
created_at>="2023-05-01"
```

The operators are `>`, `<`, `>=`, and `<=`.

### Inclusive range

```
price:100..500
created_at:"2023-01-01".."2023-12-31"
```

`price:100..500` is the same as `price>=100 AND price<=500`.

## Logical operators

```
active:true AND category:"sports" AND stock>0
category:"action" OR category:"horror"
NOT category:"sports"
active:true AND NOT stock:0
```

`AND`, `OR`, and `NOT` are case-insensitive. `AND` binds tighter than `OR`, so `a OR b AND c` means `a OR (b AND c)`. Use parentheses to group clauses:

```
active:true AND (category:"action" OR category:"horror") AND stock>0
```

> **Note:** Two clauses without an operator between them throw a `ParseException`:
>
> ```
> color:'red' size:'large'             # error
> color:'red' AND size:'large'         # OK
> ```

## Object fields

For fields inside an `object()` property, use dot notation:

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

## Nested field filtering

For a `nested()` property, put the conditions in curly braces. All conditions inside the braces must match the same item of the list:

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

This filter finds documents with one vehicle whose `make` is "Powell Motors" and whose `model` is "Canyonero". A document with a "Powell Motors" vehicle and a different "Canyonero" vehicle does not match.

Nested fields can hold nested fields:

```
contact:{ address:{ city:"Berlin" AND marker:"X" } }
```

The mapping decides the syntax:

| Mapping | Syntax |
|---------|--------|
| `object()` | `contact.active:true AND contact.city:"Berlin"` |
| `nested()` | `contact:{ active:true AND city:"Berlin" }` |

To return the nested items that matched the filter, see [inner hits](search.md#matching-nested-items-inner-hits).

## Geo-location filtering

A `geoPoint()` field matches documents within a distance of a point:

```
location:1km[51.49,13.77]
location:5mi[40.7128,-74.0060]
contact:{ location:1km[51.16,13.49] AND active:true }
```

The format is `field:distance[lat,lon]`. The distance is a number followed by a unit: `km`, `m`, `cm`, `mm`, `mi`, `yd`, `ft`, `in`, or `nmi`.

> **Note:** A distance of zero matches no documents, even on the exact coordinates. Use a small positive distance:
>
> ```
> location:0km[51.16,13.49]       # matches nothing
> location:1m[51.16,13.49]        # OK
> ```

## Quotes and special characters

Escape a quote inside a value with a backslash. A single quote inside double quotes needs no escape:

```
description:"She said \"Hello World\""
title:'It\'s working'
team:"O'Brien"
```

Quote values that contain spaces, dashes, or other special characters:

```
status:'in-progress'
category:"crime & drama"
job_title:"Chief Executive Officer (CEO)"
industry:["Renewables & Environment"]
```

## Field rules

The parser checks each field against the properties:

| Field | Rule |
|-------|------|
| Not in the properties | Error: `Field <name> does not exist.` |
| `text` without `keyword()` | Error: `Field <name> is not filterable.` |
| `text` with `keyword()` | Filters on the `.keyword` sub-field. |
| `field:{...}` on a field that is not `nested()` | Error: `Field '<name>' isn't a nested field.` |

## Errors

The parser reports errors in two ways.

Syntax errors always throw a `ParseException`:

- Two clauses without an operator between them.
- Unbalanced parentheses, brackets, braces, or quotes.
- More than 32 levels of parentheses and `NOT`. `FilterParser::$maxNestingLevel` sets the limit.

Field errors, and a clause the parser cannot read, follow the `throwOnError` setting. When it is `true`, they throw a `ParseException`. When it is `false`, the parser replaces the clause with a clause that matches nothing and adds the error to `errors()`:

```php
use Sigmie\Parse\FilterParser;

$parser = new FilterParser($props(), throwOnError: false);

$parser->parse('subject_service:{ id:"23" }');

$parser->errors();   // [['message' => "Field 'subject_service' isn't a nested field."]]
```

The default depends on the caller:

| Caller | Default |
|--------|---------|
| `new FilterParser($props)` | Throws |
| `newQuery()->parse()` | Throws |
| `newSearch()->filters()` | Collects. The errors appear under the response's `errors` key. |

To make `filters()` throw, pass `throwOnError: true`:

```php
$sigmie->newSearch('products')
    ->properties($props)
    ->filters('nonexistent:"x"', throwOnError: true);   // throws ParseException
```

A collected error still changes the results. If a broader or empty result set is unsafe, pass `throwOnError: true`, or treat a non-empty `errors` array as a failed request.

The [Laravel AI tools](laravel-ai.md#errors) always parse with `throwOnError: true`, so an agent sees the error instead of an empty result.

## Syntax summary

| Operation | Syntax | Example |
|-----------|--------|---------|
| Exact value | `field:"value"` | `category:"sports"` |
| Number | `field:123` | `price:100` |
| Boolean | `field:true` | `active:true` |
| Field exists | `field:*` | `email:*` |
| Any of | `field:[v1,v2]` | `status:["active","pending"]` |
| Wildcard | `field:'*pat*'` | `phone:'*650'` |
| Document IDs | `_id:[id1,id2]` | `_id:["a1","b2"]` |
| Range | `field:min..max` | `price:100..500` |
| Greater than | `field>value` | `stock>0` |
| Less than | `field<value` | `price<100` |
| AND | `a AND b` | `active:true AND stock>0` |
| OR | `a OR b` | `cat:"a" OR cat:"b"` |
| NOT | `NOT a` | `NOT category:"books"` |
| Object | `obj.field:value` | `contact.active:true` |
| Nested | `field:{condition}` | `contact:{active:true}` |
| Geo distance | `field:dist[lat,lon]` | `location:1km[51,13]` |
