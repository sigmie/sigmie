---
title: Laravel AI SDK
short_description: Expose Sigmie indices as Laravel AI agent tools — auto-generated descriptions, base filters for multi-tenancy, and the full Sigmie filter syntax.
keywords: [laravel ai, ai sdk, tools, agents, llm]
category: Integrations
order: 2
related_pages: [search, filter-parser, sort-parser, facets, laravel-scout]
---

# Laravel AI SDK

`SigmieIndexTool` exposes a Sigmie index as a [Laravel AI SDK](https://laravel.com/docs/ai-sdk) tool. The AI agent gets full access to your search builder — query, filters, sorts, facets, pagination — with a description auto-generated from your property definitions.

## Quick start

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

The agent now searches `products` end-to-end, with filtering, sorting, and facets.

## The `AsTool` trait

For convenience, add `AsTool` to your `SigmieIndex` subclass:

```php
use Sigmie\AI\AsTool;
use Sigmie\SigmieIndex;
use Sigmie\Mappings\NewProperties;

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
        $props->bool('in_stock');
        return $props;
    }
}
```

Now `toTool()` builds the agent tool:

```php
public function tools(): array
{
    return [
        app(ProductIndex::class)->toTool(),
    ];
}
```

## Base filters

Pass `baseFilters` to scope every query the AI makes. This is how you enforce multi-tenancy or per-user authorization — the AI can't bypass these filters and can't see them in its tool description:

```php
new SigmieIndexTool(
    app(OrderIndex::class),
    baseFilters: "user_id:{$user->id}",
);

// Or via the trait:
app(OrderIndex::class)->toTool(baseFilters: "user_id:{$user->id}");
```

Base filters are wrapped in parentheses and AND-ed with whatever the AI passes:

```
(user_id:3) AND (status:'shipped' OR status:'delivered')
```

## The auto-generated description

The tool description is built from your properties. For:

```php
$props->name('name');
$props->category('brand');
$props->number('price');
$props->boolean('in_stock');
$props->date('created_at');
```

The AI sees:

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

## Tool parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `query` | string (required) | Search query text. |
| `filters` | string | Filter expression. |
| `sort` | string | Sort expression. |
| `facets` | string | Space-separated facet fields. |
| `facet_filters` | string | Active facet filter values. |
| `per_page` | int (default 10) | Results per page. |
| `page` | int (default 1) | Page number. |

## Filter syntax

Filters use the [Filter Parser](filter-parser.md). Quick reference by field type:

### Keyword

```
brand:'toyota'
brand:['toyota','honda','ford']
brand:toy*
```

### Number / price

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

```
variants:{color:'red' AND size>10}
```

### Object

```
meta.author:'John'
```

### Combining

```
brand:'toyota' AND price:10000..50000
(brand:'toyota' OR brand:'honda') AND in_stock:true
NOT status:'discontinued'
brand:'toyota' AND NOT color:'red'
```

## Sort

Space-separated, optional `:asc` / `:desc`:

```
price:asc
created_at:desc price:asc
_score
```

Geo:

```
location[40.71,-74.00]:km:asc
```

## Facets

```
brand
brand:20 price:50
```

When facets are requested, the tool response includes a `facets` key with the aggregation data.

## See also

- [Filter Parser](filter-parser.md) — every filter operator.
- [Sort Parser](sort-parser.md) — sort expression syntax.
- [Facets](facets.md) — facet behavior and structure.
- [Search](search.md) — the underlying search builder.
- [MCP Server](mcp.md) — connect AI agents to Sigmie's own documentation.
