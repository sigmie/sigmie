---
title: Laravel AI SDK
short_description: Expose Sigmie indices as Laravel AI agent tools — auto-generated descriptions, base filters for multi-tenancy, and the full Sigmie filter syntax.
keywords: [laravel ai, ai sdk, tools, agents, llm, private fields]
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

## The tool suite

`tools()` returns the full agent tool suite for the index:

- `search_index` — query, filter, sort, facet and paginate.
- `discover_filter_values` — list valid values for a field before filtering.
- `sample_documents` — a few random documents so the agent can see the real data shape.
- `get_documents` — retrieve specific documents by their `_id` (e.g. ids surfaced by `search_index`). Non-existent ids are omitted.
- `describe_index` — the structured field list, types and filter syntax.
- `analytics` — dashboard widgets (KPIs, trends, breakdowns, distributions, percentiles).

## Base filters

Pass `baseFilters` to scope every query the AI makes. This is how you enforce multi-tenancy or per-user authorization — the AI can't bypass these filters and can't see them in its tool description:

```php
new SigmieIndexTool(
    app(OrderIndex::class),
    baseFilters: "user_id:{$user->id}",
);

// Or via the trait:
app(OrderIndex::class)->toTool(baseFilters: "user_id:{$user->id}");

// Or scope every tool at once:
app(OrderIndex::class)->tools("user_id:{$user->id}");
```

The base filter is parsed on its own and applied as a separate filter clause. The AI's `filters` string is parsed alone, so it never joins the base filter as text:

```
base filter:  user_id:3                              -> hard filter clause
AI filters:   status:'shipped' OR status:'delivered' -> second clause
match:        documents that pass both clauses
```

An AI filter with unbalanced parentheses, such as `status:'x') OR (user_id:4`, cannot turn the query into an `OR` that escapes the scope. The tool rejects it: `handle()` returns an `{"error": ...}` the agent can correct from, and `result()` throws a `ParseException`.

> **Warning:** A malformed base filter throws a `ParseException` on every call. It never falls back to matching all documents.

## Private fields

Override `exceptFromTools()` to keep fields out of every document the tools return. Dotted paths reach nested fields:

```php
class CaseIndex extends SigmieIndex
{
    use AsTool;

    public function properties(): NewProperties
    {
        $props = new NewProperties;
        $props->name('title');
        $props->nested('participants', function (NewProperties $props) {
            $props->keyword('name');
            $props->keyword('identification_number');
        });

        return $props;
    }

    public function exceptFromTools(): array
    {
        return ['participants.identification_number']; // [tl! highlight]
    }
}
```

`search_index`, `sample_documents`, `get_documents`, and the `analytics` tool's `table` widget and `include_hits` rows omit these fields. Elasticsearch drops them from `_source` before the response leaves the cluster. The exclusion wins over the agent's own `fields` and `hit_fields`, so `hit_fields: "participants"` returns `participants.name` only.

The fields are filter-only. The agent can still filter on them:

```
participants:{identification_number:'19800101-1111'}
```

Every tool refuses to list, facet, group, measure, or sort by a private field or any field below it. This covers `discover_filter_values`, the search `facets` and `sort`, and every `analytics` argument that names a field, such as `group_by`, `group_by_fields`, `row_field`, `field`, `sort`, and `hit_sort`. `handle()` returns an error the agent can correct from, and `result()` throws an `InvalidArgumentException`:

```json
{"error": "Field participants.identification_number is private and cannot be listed, grouped, faceted or sorted; you can still filter on it. ..."}
```

The tool descriptions and `describe_index` mark these fields as filter only, so the agent does not try. Your own code keeps full access: `facets()` on a search and `analytics()` on the index are unaffected.

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

## Discovering filter values

`discover_filter_values` lists the values of one facetable field, so the agent filters on real values instead of guessing. It returns at most `limit` values (default 20). When more values exist, `truncated` is `true` and `other_documents` counts the documents in the values that were left out:

```json
{
    "field": "color",
    "values": { "red": 12, "blue": 7 },
    "truncated": true, // [tl! highlight]
    "other_documents": 5
}
```

Here `limit` was 2, and 5 more documents have other colors. Before it treats the list as complete (for example, to count colors), the agent raises `limit` or narrows the list with `filters`, such as `"color:b*"`. Numeric and date fields return min/max and always report `truncated: false`.

## Errors

The tools parse the agent's `filters`, `facets` and `facet_filters` strictly. An unknown field or unparseable expression returns an `error` result the agent can read and correct, instead of an empty result:

```
filters: color:'red'    (no "color" field)

before: {"total": 0, "hits": []}
after:  {"error": "Field color does not exist. Check the field names and the filter/sort syntax in this tool's description, then try again."}
```

The same applies to `discover_filter_values` for its `field` and `filters`.

Only the agent-facing `handle()` returns errors as JSON. The structured `result()` methods throw, so programmatic callers keep normal exception handling:

```php
use Laravel\Ai\Tools\Request;
use Sigmie\AI\SigmieFilterValuesTool;
use Sigmie\Parse\ParseException;

try {
    (new SigmieFilterValuesTool($index))->result(new Request(['field' => 'nope']));
} catch (ParseException $e) {
    // "Field nope does not exist."
}
```

## See also

- [Filter Parser](filter-parser.md) — every filter operator.
- [Sort Parser](sort-parser.md) — sort expression syntax.
- [Facets](facets.md) — facet behavior and structure.
- [Search](search.md) — the underlying search builder.
- [MCP Server](mcp.md) — connect AI agents to Sigmie's own documentation.
