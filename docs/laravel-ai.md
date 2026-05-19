---
title: Laravel AI SDK
short_description: Expose Sigmie indices as tools for Laravel AI agents
keywords: [laravel ai, ai sdk, tools, agents, llm, ai tools]
category: Integrations
order: 2
related_pages: [search, filter-parser, sort-parser, facets, laravel-scout]
---

# Laravel AI SDK

## Introduction

When building AI agents, you often need the LLM to query or find documents in an index. Sigmie integrates with the [Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk) so you can expose any index as a tool — the AI gets search, filtering, sorting, and faceting out of the box.

The `SigmieIndexTool` class wraps a `SigmieIndex` and implements Laravel AI's `Tool` interface. It auto-generates a description from your index properties, so the AI knows what fields exist and how to filter on each one.

## Quick Start

```php
use Sigmie\AI\SigmieIndexTool;

class ShoppingAssistant implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return 'You help users find products in our catalog.';
    }

    public function tools(): array
    {
        return [
            new SigmieIndexTool(app(ProductIndex::class)),
        ];
    }
}
```

That's it. The AI can now search the `products` index, filter by any field, sort results, and request facets.

## The `AsTool` Trait

For convenience, add the `AsTool` trait to your index class and call `toTool()`:

```php
use Sigmie\AI\AsTool;
use Sigmie\SigmieIndex;

class ProductIndex extends SigmieIndex
{
    use AsTool;

    public function name(): string
    {
        return 'products';
    }

    public function properties(): NewProperties
    {
        $props = new NewProperties;
        $props->name('name');
        $props->category('brand');
        $props->number('price');
        $props->boolean('in_stock');
        return $props;
    }
}
```

```php
public function tools(): array
{
    return [
        app(ProductIndex::class)->toTool(),
    ];
}
```

## Base Filters

Pass `baseFilters` to scope every query the AI makes. This is useful for multi-tenancy or authorization — the AI never sees or can bypass these filters:

```php
new SigmieIndexTool(
    app(OrderIndex::class),
    baseFilters: "user_id:{$user->id}",
)

// or via the trait
app(OrderIndex::class)->toTool(baseFilters: "user_id:{$user->id}")
```

Base filters are wrapped in parentheses and AND'd with the AI's filters, so precedence is always correct:

```
(user_id:3) AND (status:'shipped' OR status:'delivered')
```

## Auto-Generated Description

The tool description is built from your index properties. The AI sees the field names, types, capabilities, and filter syntax examples. For an index with:

```php
$props->name('name');
$props->category('brand');
$props->number('price');
$props->boolean('in_stock');
$props->date('created_at');
```

The generated description looks like:

```
Search the 'products' index.

Available fields:
- name [text] (sortable, facetable): name:'value' name:['a','b']
- brand [text] (sortable, facetable): brand:'value' brand:['a','b']
- price [number] (sortable, facetable): price>n price<=n price:min..max
- in_stock [boolean] (sortable): in_stock:true in_stock:false
- created_at [date] (sortable): created_at>'2024-01-01' created_at<'2024-12-31'

Filter operators: AND, OR, AND NOT
Negation: NOT field:'value'
Grouping: (field:'a' OR field:'b') AND other>10
Exists check: field:*
Sort: field:asc field:desc _score (space-separated)
Geo sort: field[lat,lon]:km:asc
Facets: field1 field2:20 (space-separated, optional :size for keywords or :interval for numbers)
```

## Tool Parameters

The AI receives these parameters in the tool schema:

| Parameter | Type | Description |
|-----------|------|-------------|
| `query` | string (required) | Search query text |
| `filters` | string | Filter expression |
| `sort` | string | Sort expression |
| `facets` | string | Space-separated facet fields |
| `facet_filters` | string | Active facet filter values |
| `per_page` | integer (default 10) | Results per page |
| `page` | integer (default 1) | Page number |

## Filter Syntax by Field Type

The filter syntax follows the [Filter Parser](filter-parser.md) rules. Each field type supports different operators:

### Keyword

```
brand:'toyota'
brand:['toyota','honda','ford']
brand:toy*
```

### Number / Price

```
price>100
price<=50
price:10..100
```

### Boolean

```
in_stock:true
in_stock:false
```

### Date

```
created_at>'2024-01-01'
created_at<'2024-12-31'
```

### Geo

```
location:10km[40.71,-74.00]
```

### Nested

Nested fields use curly braces. Sub-field filters go inside:

```
variants:{color:'red' AND size>10}
```

### Object

Object fields use dot notation, just like regular fields:

```
meta.author:'John'
```

### Combining Filters

```
brand:'toyota' AND price:10000..50000
(brand:'toyota' OR brand:'honda') AND in_stock:true
NOT status:'discontinued'
brand:'toyota' AND NOT color:'red'
```

## Sorting

Sort expressions are space-separated. Each field can specify `:asc` or `:desc`:

```
price:asc
created_at:desc price:asc
_score
```

Geo fields have a special sort syntax:

```
location[40.71,-74.00]:km:asc
```

## Facets

Request facets by listing field names. Optionally pass a size (for keywords) or interval (for numbers) after a colon:

```
brand
brand:20 price:50
```

The tool response includes a `facets` key with aggregation data when facets are requested.
