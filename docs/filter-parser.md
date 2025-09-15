## Filtering 

Creating boolean queries can be complex, especially when your primary goal is to filter results.
To simplify this process, we've developed our own filtering language.

This language serves as a developer-friendly interface for constructing boolean queries, with the goal of reducing the likelihood of errors.

For instance, if you want to search for movies that are currently active, in stock, and belong to either the `action` or `horror` category, you can write:

```sql
is:active AND (category:"action" OR category:"horror") AND NOT stock:0
```

@warning
It's important to note that filtering isn't possible on **all** mapping types.

Visit the [Mapping](/docs/v0/mappings) section of this documentation for more details.
@endwarning

Now, let's delve deeper into the syntax and understand how to use it effectively.

### Syntax

Filter clauses can be combined using logical operators to create complex queries.

Here are the operators you can use:
* `AND`: Used to **combine two or more** filters. **Only documents that meet all conditions will be matched**.
* `OR`: Used to match documents that meet **at least one of the conditions**.
* `AND NOT`: Used to **exclude** documents that meet a certain condition.

Using these operators effectively will help you create precise and powerful filter queries.

Spaces are used to separate logical operators and filter clauses:

```bash
{filter_clause} AND {filter_clause}
```

To specify the order of execution, you can use **parentheses** to group clauses in a filter query.

Consider this example:

```sql
is:active AND (category:"action" OR category:"horror") AND NOT stock:0
```

Here, the **AND** operator joins three distinct clauses:
* `is:active`
* `(category:"action" OR category:"horror")`
* `NOT stock:0`

The parentheses indicate that the **OR** operator applies only to the category field clauses, not the entire query.

This query will return all items that are active, belong to either the "action" or "horror" categories, and are not out of stock.

### Negative Filtering

To construct a **negative** filter, prefix the filter value with `NOT`:

```bash
NOT {filter_clause}
```

For instance, to exclude documents in the "Sports" category:
```sql
NOT category:'Sports'
```

### Equals

```bash
{field}:"{value}"
``` 

This syntax filters for specific values. Use it when you need to narrow down your search based on a specific field value.

The `{field}` placeholder represents the field name. 

Consider this document structure:
```json
{
 "color": "..."
}
```

To filter for documents with the color red:

```sql
color:'red'
``` 

For strings, the `{value}` must be enclosed in double `"` or single quotes `'`.
You can escape quotes inside the `{value}` using a **backslash** `\`.

### Boolean Field Filtering

Boolean fields can be filtered using two different syntaxes, giving you flexibility in how you write your filter expressions.

#### Direct Boolean Value Syntax

You can filter boolean fields directly using `true` or `false` values:

```bash
{field}:true
{field}:false
```

For example, given a document structure:
```json
{
  "active": true,
  "published": false
}
```

You can filter for active documents:
```sql
active:true
```

Or filter for inactive documents:
```sql
active:false
```

#### Using Boolean Values in Complex Queries

Boolean filters work seamlessly with logical operators:

```sql
# Find active and published documents
active:true AND published:true

# Find documents that are either active OR published
active:true OR published:true

# Find active documents that are NOT published
active:true AND NOT published:true

# Complex boolean filtering with other field types
active:true AND category:"sports" AND stock>0
```

#### Boolean Values in Nested Objects

Boolean filtering also works with nested object properties using dot notation:

```sql
# For object properties
contact.active:true
contact.is_verified:false

# Combining nested boolean with other conditions
contact.active:true AND contact.name:"John Doe"
```

For deeply nested structures, you can chain the dot notation:
```sql
user.profile.active:true
user.settings.notifications:false
```

#### Boolean Values in Nested Fields

For true nested fields (not object properties), use the curly brace syntax:

```sql
# Single boolean condition in nested field
contact:{ active:true }

# Multiple conditions in nested field
contact:{ active:true AND verified:false }

# Complex nested boolean filtering
contact:{ active:true AND location:1km[51.16,13.49] }
```

#### Real-World Boolean Filtering Examples

Based on practical use cases, here are some comprehensive examples:

```sql
# E-commerce product filtering
active:true AND stock>0 AND (category:"electronics" OR category:"computers")

# User management with nested object properties
contact.is_active:true AND contact.is_verified:false AND contact.category:"premium"

# Complex nested filtering with boolean conditions
driver.vehicle:{ active:true AND model:"Canyonero" } AND driver.license:true

# Multi-level boolean conditions
contact:{ active:true AND verified:true } AND account.premium:true AND status:"confirmed"
```

### is & is_not

The `is:` and `is_not:` operators provide an alternative, more semantic way to filter `boolean` fields.

The `is` operator matches documents where the specified **boolean** field value is `true`.

```bash
is:{field}
```

Conversely, the `is_not` operator matches documents where the specified **boolean** field value is `false`.

```bash
is_not:{field}
```

For instance, consider the following document structure:
```json
{
  "active": true
}
```

In this case, you can use:

```sql
is:active
```

to match documents that are **active**, and:

```sql
is_not:active
```

to match documents that **are NOT** active.

#### Equivalence Between Syntaxes

The following filter expressions are equivalent:

```sql
# These all match documents where active=true
active:true
is:active

# These all match documents where active=false  
active:false
is_not:active

# These all exclude active documents
NOT active:true
NOT is:active
active:false
is_not:active
```

Choose the syntax that feels most natural for your use case. The `is:` and `is_not:` operators can make filters more readable, especially in complex queries:

```sql
# Using direct boolean syntax
active:true AND published:true AND featured:false

# Using semantic operators (equivalent)
is:active AND is:published AND is_not:featured
```

### Important Notes for Boolean Filtering

1. **No Quotes Required**: Boolean values (`true`/`false`) don't require quotes:
   ```sql
   # ✅ Correct
   active:true
   published:false
   
   # ⚠️ Also works, but unnecessary
   active:"true"
   published:"false"
   ```

2. **Case Sensitivity**: Boolean values are case-sensitive and must be lowercase:
   ```sql
   # ✅ Correct
   active:true
   active:false
   
   # ❌ Will not work as expected
   active:True
   active:FALSE
   active:TRUE
   ```

3. **Type Validation**: The filter parser validates that boolean syntax is only used with actual boolean fields defined in your mappings.

4. **Performance**: Boolean filters are typically very fast as they create efficient term queries in Elasticsearch.

5. **Nested Field Behavior**: When using boolean filters in nested fields, each nested document is evaluated independently:
   ```sql
   # This matches any nested document where both conditions are true in the same nested object
   contact:{ active:true AND verified:true }
   
   # This is different from object properties where conditions apply to the parent document
   contact.active:true AND contact.verified:true
   ```

### In

The `in` operator is useful when you need to filter a field for multiple values.

```bash
{field}:[{value1}, {value2}]
```

For instance, given the previous document structure, if you want to find documents with the **color** `red` or `blue`, you can use:

```bash
category:['red', 'blue']
```

### Wildcard Filtering

You can use wildcard patterns to match partial values using the `*` character:

```bash
{field}:'*{pattern}'    # Match ending with pattern
{field}:'{pattern}*'    # Match starting with pattern  
{field}:'*{pattern}*'   # Match containing pattern
```

Examples:
```sql
# Match phone numbers ending with specific digits
number:'*650'

# Match phone numbers starting with area code
number:'2353*'

# Check for field existence (has any value)
field_name:*

# Check for field non-existence  
NOT field_name:*
```

This is particularly useful for:
- Partial phone number matching
- Prefix/suffix searching on identifiers
- Checking if fields have any value

### Range Filtering

Often, you may need to filter data within a certain range. This can be achieved using the **range operators**.

Here is a list of **valid** range operators and their corresponding meanings:
*  `>` - **greater than**.
* `<` - **less than**.
* `<=` - **less than or equal to**.
* `>=` - **greater than or equal to**.

The syntax for using **range operators** is as follows:

```bash
{field}{operator}{value} 
```

**Range operators** can be used for both **Dates** and **Numbers**.

For instance, consider the following document structure:
```php
{
 "created_at": "2023-08-01",
 "price": 199
}
```
By using the filter:
```sql
created_at>="2023-05-01" AND created_at<="2023-10-01"
```
you can filter documents where the `created_at` date field is **greater than or equal to** `2023-05-01` and **less than or equal to** `2023-10-01`.

The same syntax can be used to filter within a specific **price range**.

```sql
price>=100 AND price<=200
```

### Between Range Syntax

For convenience, you can also use the **between** syntax with `..` (double dot) to specify ranges:

```bash
{field}:{from_value}..{to_value}
```

This is equivalent to using `>=` and `<=` operators but more concise:

```sql
price:100..200
```

This is the same as writing:
```sql
price>=100 AND price<=200
```

The between syntax works with both numbers and dates:

```sql
# Number ranges
price:100..500
stock:10..100

# Date ranges  
created_at:2023-01-01T00:00:00.000000+00:00..2023-12-31T23:59:59.999999+00:00
last_activity:2024-01-01..2024-12-31
```

## Value Quoting Rules

### Numbers and Quotes

Numbers can be used with or without quotes in most contexts:

```sql
# Both are valid for number fields
price:100
price:"100"

# Same for comparisons
stock>=50
stock>="50"

# Range syntax
price:100..200    # No quotes needed
```

### String Values

String values should be quoted, but both single and double quotes are supported:

```sql
# Both are equivalent  
category:"sports"
category:'sports'

# Complex strings with special characters
job_title:"Chief Information Officer (CIO)"
category:'crime & drama'
```

### Empty Values

You can filter for empty values using empty quotes:

```sql
# Find documents with empty database field
database:""
database:''

# Find documents with empty array
tags:[]
```

### Escaping Special Characters

Use backslashes to escape quotes within quoted strings:

```sql
description:"She said \"Hello World\""
title:'It\'s working'
```


## Sorting

To further refine your search results, you can use our intuitive sorting language.

Here's how you can use it:

```bash
_score rating:desc name:asc
```

In this example, the results are first sorted by the relevance score, then by rating in a descending order,
and lastly by name in an ascending order.

@info
The `_score` is a unique sorting attribute that arranges the results in a descending order,
based on their computed relevance score.
@endinfo

Sorting clauses are divided by spaces, and follow this syntax:
```sql
{attribute}:{direction}
```

The `direction` can be either `asc` for ascending order or `desc` for descending order.

# Filtering Nested Properties

When working with nested properties in your Elasticsearch documents, you can use a special syntax to filter based on nested field values. The syntax supports both simple and complex nested property filtering.

## Basic Nested Property Filtering

To filter nested properties, use the following syntax:
```sql
property_name:{ field:"value" }
```

For example, if you have a nested field called `subject_services` with `id` and `name` properties:

```php
[
    'subject_services' => [
        ['name' => 'BMAT', 'id' => 23],
        ['name' => 'IMAT', 'id' => 24]
    ]
]
```

You can filter for specific values like this:
```sql
subject_services:{ id:"23" }
```

## Multiple Conditions in Nested Filters

You can combine multiple conditions within a nested filter using AND/OR operators:

```sql
subject_services:{ id:"23" AND name:"BMAT" }
```

## Deep Nested Properties

For deeply nested properties (nested fields within nested fields), you can use multiple levels of curly braces:

```
contact:{ address:{ city:"Berlin" AND marker:"X" } }
```

This would match documents with this structure:
```php
[
    'contact' => [
        'address' => [
            [
                'city' => 'Berlin',
                'marker' => 'X'
            ]
        ]
    ]
]
```

## Object Properties vs Nested Properties

It's important to note the difference between object properties and nested properties:

- **For object properties**, use dot notation:
```sql
contact.active:true
contact.name:"John Doe"
contact.location.lat:40.7128
```

- **For nested properties**, use the curly brace syntax:
```sql
contact:{ active:true }
contact:{ name:"John Doe" AND location:1km[40.7128,-74.0060] }
```

### Mixed Nested Path Syntax

For complex nested structures, you can also use the mixed dot and curly brace syntax:

```sql
# For nested fields within nested fields
driver.vehicle:{ make:"Powell Motors" AND model:"Canyonero" }

# For deeply nested structures
subject_services:{ id:"23" AND name:"BMAT" }
```

## Combining Nested Filters

You can combine nested filters with other filter types using AND/OR operators:

```sql
subject_services:{ id:"23" } AND category:"active"
```

Remember that nested filters are powerful but should be used judiciously, as they can impact query performance, especially with deeply nested structures or complex conditions.

# Geo-Location Filtering

Elasticsearch provides powerful geo-location filtering capabilities that allow you to search for documents within a specific distance from a given point. 

## Basic Syntax

The basic syntax for geo-location filtering is:
```
location:distance[latitude,longitude]
```

Where:
- `location` is your geo-point field name
- `distance` is the radius with a unit (see supported units below)
- `latitude` and `longitude` are the coordinates of the center point

## Distance Units

You can specify distances using various units:
- Kilometers: `km`
- Miles: `mi`
- Meters: `m`
- Yards: `yd`
- Feet: `ft`
- Nautical Miles: `nmi`
- Centimeters: `cm`
- Inches: `in`

Examples:
```
location:70km[52.31,8.61]
location:5mi[-33.8688,151.2093]
location:100m[40.7128,-74.0060]
location:500yd[35.6762,139.6503]
location:1000ft[55.7558,37.6173]
location:10nmi[-22.9068,-43.1729]
location:50cm[-1.2921,36.8219]
location:3in[41.9028,12.4964]
```

## Document Structure

Your documents should store geo-points in this format:
```php
[
    'location' => [
        'lat' => 51.16,
        'lon' => 13.49
    ]
]
```

## Example Usage

To find documents within 1 kilometer of a specific point:
```
location:1km[51.49,13.77]
```

## Combining with Other Filters

You can combine geo-location filters with other filters using AND/OR operators:
```
location:1km[51.49,13.77] AND is:active
```

## Important Notes

1. Distance of Zero:
   - Using `location:0km[lat,lon]` will not return any results, even for exact matches
   - Always use a small positive distance for exact location matching

2. Precision:
   - You can use decimal points in coordinates for more precise locations
   - Example: `location:2km[51.16,13.49]` vs `location:2km[51,13]`

3. Performance:
   - Geo-location queries can be computationally expensive
   - Consider using appropriate distances based on your use case
   - Very large distances (like `2000000000mi`) might impact performance

## Nested Geo-Location Filters

You can also use geo-location filters with nested fields:
```
contact:{ location:1km[51.16,13.49] }
```

For a document structure like:
```php
[
    'contact' => [
        'location' => [
            'lat' => 51.16,
            'lon' => 13.49
        ]
    ]
]
```

Remember that geo-location filtering is particularly useful for:
- Finding nearby locations
- Territory-based searches
- Distance-based filtering
- Geographic boundary queries

## Important Limitations and Best Practices

### Syntax Requirements

1. **Logical Operators Must Be Separated**: Always separate filter clauses with proper logical operators:
   ```sql
   # ✅ Correct
   color:'red' AND category:'sports'
   
   # ❌ Incorrect - will throw ParseException
   color:'red' color:'sports'
   ```

2. **Property Validation**: The filter parser validates that all referenced fields exist in your mappings. Using non-existent fields will result in errors.

3. **Complex Nested Filters**: There are limits to nesting depth to prevent performance issues:
   ```sql
   # ✅ Reasonable nesting
   (category:'action' OR category:'horror') AND stock>0
   
   # ❌ Excessive nesting (will throw ParseException)
   (((((((((((((((((NOT field:'value'))))))))))))))))
   ```

### Performance Considerations

1. **Geo-location Distance**: Very large distances can impact performance:
   ```sql
   # ✅ Reasonable distance
   location:100km[51.49,13.77]
   
   # ⚠️ Very large distance - may be slow  
   location:2000000000mi[51.49,13.77]
   ```

2. **Zero Distance**: Using zero distance in geo-location filters returns no results, even for exact matches:
   ```sql
   # ❌ Returns no results
   location:0km[51.16,13.49]
   
   # ✅ Use small positive distance instead
   location:1m[51.16,13.49]
   ```

3. **Empty Arrays**: Empty `IN` arrays return no results:
   ```sql
   # Returns no documents
   category:[]
   ```

### Quote Handling

1. **Parentheses in Quotes**: Parentheses inside quoted strings are preserved and don't affect parsing:
   ```sql
   # ✅ Correctly handled
   title:"Chief Executive Officer (CEO)"
   job_titles:["Chief Information Officer (CIO)"]
   ```

2. **Whitespace Trimming**: Values in arrays are automatically trimmed:
   ```sql
   # These are equivalent
   ids:['123', '456', '789']
   ids:[' 123 ', ' 456 ', ' 789 ']
   ```