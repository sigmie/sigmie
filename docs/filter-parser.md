# Filter Parser

The Filter Parser provides a human-readable query language for building complex Elasticsearch filters. Instead of manually constructing boolean queries, you write intuitive filter expressions that get parsed into optimized Elasticsearch queries.

## Why Use the Filter Parser?

Creating boolean queries can be complex and error-prone. The Filter Parser simplifies this by providing:

- **Intuitive syntax**: Write filters that read like natural language
- **Type safety**: Automatic validation against your index mappings
- **Error prevention**: Catches syntax errors before they reach Elasticsearch
- **Developer friendly**: No need to understand Elasticsearch's boolean query DSL

## Getting Started

The Filter Parser requires your index properties to validate field types and ensure correct query construction.

```php
use Sigmie\Parse\FilterParser;
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->keyword('category');
$props->bool('active');
$props->number('stock');

$parser = new FilterParser($props());
$query = $parser->parse('active:true AND category:"sports"');

// Use the query with your search
$results = $sigmie->query($indexName, $query)->get();
```

This example creates a filter that finds active documents in the sports category.

## Basic Field Filtering

### Exact Match

Filter for specific values using the colon operator:

```sql
category:"sports"
color:'red'
```

Both single and double quotes work. Use whichever feels natural:

```sql
name:"John Doe"
name:'John Doe'  # Equivalent
```

### Numbers

Numbers can be used with or without quotes:

```sql
price:100
stock:50
quantity:"25"  # Also works
```

### Boolean Values

Filter boolean fields using `true` or `false`:

```sql
active:true
published:false
verified:true
```

Boolean values must be lowercase and don't require quotes.

### Field Existence

Check if a field has any value using the wildcard operator:

```sql
# Has a value
email:*

# Does not have a value
NOT email:*
```

This is useful for finding documents with missing or populated fields.

## Multiple Values (IN Operator)

Match any of several values using array syntax:

```sql
category:["sports", "action", "horror"]
color:['red', 'blue', 'green']
status:["active", "pending"]
```

Arrays automatically trim whitespace:

```sql
# These are equivalent
ids:['123', '456', '789']
ids:[' 123 ', ' 456 ', ' 789 ']
```

**Note**: Empty arrays return no results:

```sql
category:[]  # Returns nothing
```

## Wildcard Matching

Use the `*` character for partial matches:

```sql
# Match ending pattern
phone:'*650'

# Match starting pattern
phone:'2353*'

# Match containing pattern
title:'*manager*'
```

Real-world example:

```php
$props = new NewProperties;
$props->searchableNumber('phone');

$parser = new FilterParser($props(), false);

// Find phone numbers ending in 650
$query = $parser->parse("phone:'*650'");
```

## Range Filtering

### Comparison Operators

Filter numeric and date ranges using comparison operators:

```sql
# Numbers
price>=100
price<=200
stock>0

# Dates
created_at>="2023-05-01"
created_at<="2023-08-01"
```

Available operators:
- `>` - greater than
- `<` - less than
- `>=` - greater than or equal to
- `<=` - less than or equal to

### Between Syntax

Use the `..` operator for inclusive ranges:

```sql
# Number ranges
price:100..200
stock:10..100

# Date ranges
created_at:"2023-01-01".."2023-12-31"
last_activity:2024-01-01T00:00:00+00:00..2024-12-31T23:59:59+00:00
```

This is equivalent to:

```sql
price:100..200
# Same as:
price>=100 AND price<=200
```

Real-world example:

```php
$props = new NewProperties;
$props->number('price');
$props->date('created_at');

$parser = new FilterParser($props());

// Between syntax
$query = $parser->parse('price:100..500');

// Equivalent comparison operators
$query = $parser->parse('price>=100 AND price<=500');
```

## Logical Operators

Combine filters using logical operators.

### AND Operator

Match documents that satisfy **all** conditions:

```sql
active:true AND category:"sports" AND stock>0
```

### OR Operator

Match documents that satisfy **at least one** condition:

```sql
category:"action" OR category:"horror"
status:"pending" OR status:"approved"
```

### NOT Operator

Exclude documents matching a condition:

```sql
NOT category:"sports"
active:true AND NOT stock:0
```

### Combining Operators

Use parentheses to control evaluation order:

```sql
active:true AND (category:"action" OR category:"horror") AND stock>0
```

This matches documents that are:
- Active
- In either the action OR horror category
- In stock

**Important**: Multiple filters without operators will throw an error:

```sql
# ✅ Correct
color:'red' AND size:'large'

# ❌ Throws ParseException
color:'red' size:'large'
```

## Complex Query Examples

### E-commerce Product Filter

```sql
active:true AND stock>0 AND (category:"electronics" OR category:"computers") AND price:100..500
```

### User Activity Filter

```sql
(
    (emails_sent>0 AND last_activity>="2024-01-01")
    OR
    (clicks>=5 AND visits>=10)
)
AND account_status:"active"
```

### Exclusion Filter

```sql
active:true AND NOT (category:"drama" OR category:"horror")
```

This finds active documents that are neither drama nor horror.

## Object Properties

For object fields (flattened structures), use dot notation:

```sql
contact.active:true
contact.name:"John Doe"
user.email:"john@example.com"
```

You can combine multiple object properties:

```sql
contact.active:true AND contact.email:"john@example.com" AND contact.verified:false
```

Deep object paths work seamlessly:

```sql
user.profile.settings.notifications:true
order.shipping.address.city:"Berlin"
```

Example with all field types:

```php
$props = new NewProperties;
$props->object('contact', function (NewProperties $p) {
    $p->bool('active');
    $p->name('name');
    $p->email('email');
    $p->number('age');
    $p->keyword('status');
});

$parser = new FilterParser($props());

$query = $parser->parse(
    'contact.active:true AND contact.name:"Alice" AND contact.age>=25'
);
```

## Nested Field Filtering

For true nested fields (arrays of objects), use curly brace syntax:

```sql
contact:{ active:true }
contact:{ name:"John Doe" AND verified:true }
```

### Single Condition

```sql
subject_services:{ id:"23" }
```

Matches documents like:

```php
[
    'subject_services' => [
        ['name' => 'BMAT', 'id' => 23],
        ['name' => 'IMAT', 'id' => 24],
    ]
]
```

### Multiple Conditions

All conditions must match within the **same** nested object:

```sql
driver.vehicle:{ make:"Powell Motors" AND model:"Canyonero" }
```

This ensures both `make` and `model` match in the same vehicle, not across different vehicles.

### Deep Nesting

Nest filters for multi-level structures:

```sql
contact:{ address:{ city:"Berlin" AND marker:"X" } }
```

Matches:

```php
[
    'contact' => [
        'address' => [
            [
                'city' => 'Berlin',
                'marker' => 'X'
            ],
            [
                'city' => 'Hamburg',
                'marker' => 'A'
            ]
        ]
    ]
]
```

### Object vs Nested Syntax

**Objects** use dot notation (flat structure):

```sql
contact.active:true AND contact.city:"Berlin"
```

**Nested** uses curly braces (array structure with relationship preservation):

```sql
contact:{ active:true AND city:"Berlin" }
```

The nested syntax ensures conditions match within the same array element.

Real-world example:

```php
$props = new NewProperties;
$props->nested('driver', function (NewProperties $p) {
    $p->name('name');
    $p->nested('vehicle', function (NewProperties $p) {
        $p->keyword('make');
        $p->keyword('model');
    });
});

$parser = new FilterParser($props());

// Find drivers with specific vehicle
$query = $parser->parse(
    "driver.vehicle:{ make:'Powell Motors' AND model:'Canyonero' }"
);
```

## Geo-Location Filtering

Filter documents by geographic proximity.

### Basic Syntax

```sql
location:distance[latitude,longitude]
```

Example:

```sql
location:1km[51.49,13.77]
location:5mi[40.7128,-74.0060]
```

### Supported Distance Units

- Kilometers: `km`
- Miles: `mi`
- Meters: `m`
- Yards: `yd`
- Feet: `ft`
- Nautical Miles: `nmi`
- Centimeters: `cm`
- Inches: `in`

### Document Structure

Store locations as geo-points:

```php
[
    'location' => [
        'lat' => 51.16,
        'lon' => 13.49
    ]
]
```

### Real-World Examples

```php
$props = new NewProperties;
$props->geoPoint('location');

$parser = new FilterParser($props());

// Find locations within 100km
$query = $parser->parse('location:100km[51.34,12.32]');

// Combine with other filters
$query = $parser->parse('location:1km[51.49,13.77] AND active:true');
```

### Nested Geo-Locations

Geo-filters work inside nested fields:

```sql
contact:{ active:true AND location:1km[51.16,13.49] }
```

**Important**: Zero distance returns no results, even for exact matches. Use a small positive value:

```sql
# ❌ Returns nothing
location:0km[51.16,13.49]

# ✅ Use small distance
location:1m[51.16,13.49]
```

## Empty Values

Filter for empty or missing values.

### Empty Strings

```sql
database:""
database:''
```

### Empty Arrays

```sql
tags:[]
categories:[]
```

**Note**: Empty arrays in filters return no results.

## Special Characters

### Escaping Quotes

Use backslashes to escape quotes inside strings:

```sql
description:"She said \"Hello World\""
title:'It\'s working'
```

### Dashes and Spaces

Dashes and spaces in values require quotes:

```sql
status:'in-progress'
category:"crime & drama"
title:"Chief Information Officer (CIO)"
```

Parentheses inside quotes are preserved:

```sql
job_title:"Chief Executive Officer (CEO)"
industry:["Renewables & Environment"]
```

## Error Handling

### Parse Exceptions

The parser validates syntax and throws `ParseException` for invalid queries:

```php
use Sigmie\Parse\ParseException;

try {
    $query = $parser->parse('color:"red" color:"blue"'); // Missing operator
} catch (ParseException $e) {
    // Handle syntax error
}
```

Common errors:
- Missing logical operators between clauses
- Non-existent fields in mappings
- Excessive nesting depth
- Mismatched parentheses

### Field Validation

The parser validates fields against your mappings:

```php
$props = new NewProperties;
$props->keyword('category');

$parser = new FilterParser($props());

// ✅ Works - field exists
$query = $parser->parse('category:"sports"');

// ❌ Error - field doesn't exist
$query = $parser->parse('subject_service:{ id:"23" }');

// Check for errors
if (!empty($parser->errors())) {
    // Handle validation errors
}
```

## Performance Considerations

### Nesting Depth

Avoid excessive nesting to maintain performance:

```sql
# ✅ Reasonable complexity
(category:'action' OR category:'horror') AND active:true

# ❌ Excessive nesting (throws ParseException)
(((((((((((((((((NOT field:'value'))))))))))))))))
```

### Geo-Location Distance

Large distances can impact performance:

```sql
# ✅ Reasonable
location:100km[51.49,13.77]

# ⚠️ May be slow
location:2000000000mi[51.49,13.77]
```

### Boolean Filters

Boolean filters are typically fast and efficient:

```sql
active:true AND verified:true AND published:false
```

## Complete Real-World Examples

### Product Search

```php
$props = new NewProperties;
$props->keyword('category');
$props->bool('active');
$props->number('stock');
$props->number('price');

$parser = new FilterParser($props());

$query = $parser->parse(
    'active:true AND stock>0 AND (category:"electronics" OR category:"computers") AND price:100..500'
);

$results = $sigmie->query('products', $query)->get();
```

### User Management

```php
$props = new NewProperties;
$props->object('contact', function (NewProperties $p) {
    $p->bool('active');
    $p->bool('verified');
    $p->email('email');
    $p->geoPoint('location');
});

$parser = new FilterParser($props());

$query = $parser->parse(
    'contact.active:true AND contact.verified:false AND contact.location:10km[51.16,13.49]'
);

$results = $sigmie->query('users', $query)->get();
```

### Advanced Nested Filtering

```php
$props = new NewProperties;
$props->nested('driver', function (NewProperties $p) {
    $p->name('name');
    $p->nested('vehicle', function (NewProperties $p) {
        $p->keyword('make');
        $p->keyword('model');
        $p->bool('active');
    });
});

$parser = new FilterParser($props());

$query = $parser->parse(
    "driver.vehicle:{ make:'Powell Motors' AND model:'Canyonero' AND active:true }"
);

$results = $sigmie->query('drivers', $query)->get();
```

### Time-Based Activity Filter

```php
$props = new NewProperties;
$props->date('last_activity');
$props->number('emails_click_count');
$props->number('finished_surveys_count');
$props->number('account_id');

$parser = new FilterParser($props());

$query = $parser->parse(
    "(emails_click_count:1..3 AND finished_surveys_count>=5)
    AND last_activity>='2024-02-12T00:00:00+00:00'
    AND last_activity<='2024-03-13T23:59:59+00:00'
    AND account_id:'2'"
);

$results = $sigmie->query('activity', $query)->get();
```

## Syntax Quick Reference

| Operation | Syntax | Example |
|-----------|--------|---------|
| Exact match | `field:"value"` | `category:"sports"` |
| Number | `field:123` | `price:100` |
| Boolean | `field:true` | `active:true` |
| Field exists | `field:*` | `email:*` |
| IN operator | `field:[val1,val2]` | `status:["active","pending"]` |
| Wildcard | `field:'*pattern*'` | `phone:'*650'` |
| Range | `field:min..max` | `price:100..500` |
| Greater than | `field>value` | `stock>0` |
| Less than | `field<value` | `price<100` |
| AND | `clause AND clause` | `active:true AND stock>0` |
| OR | `clause OR clause` | `cat:"a" OR cat:"b"` |
| NOT | `NOT clause` | `NOT category:"sports"` |
| Object | `obj.field:value` | `contact.active:true` |
| Nested | `field:{condition}` | `contact:{active:true}` |
| Geo-location | `field:dist[lat,lon]` | `location:1km[51,13]` |

## Next Steps

- Learn about [Search](/docs/search) to use filters in queries
- Explore [Mappings](/docs/mappings) to define filterable fields
- Read about [Aggregations](/docs/aggregations) for faceted search
