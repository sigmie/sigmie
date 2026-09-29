---
title: Laravel AI SDK
short_description: Expose Sigmie indices as Laravel AI agent tools — auto-generated descriptions, base filters for multi-tenancy, and the full Sigmie filter syntax.
keywords: [laravel ai, ai sdk, tools, agents, llm, private fields, tool output, inner hits]
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
- `sample_documents` — a few random documents from inside the base filter, so the agent can see the real data shape.
- `get_documents` — retrieve specific documents by their `_id` (e.g. ids surfaced by `search_index`). Ids that do not exist or fall outside the base filter are omitted.
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

Every tool in the suite applies the base filter, including `sample_documents` and `get_documents`. Sampling draws only from in-scope documents, and an out-of-scope id returns the same result as a missing id:

```php
[, , $sample, $get] = app(OrderIndex::class)->tools('user_id:3');

$get->handle(new Request(['ids' => ['order-of-user-3', 'order-of-user-4']]));
// [{"_id": "order-of-user-3", ...}]  (order-of-user-4 is omitted, like a missing id)
```

> **Warning:** A malformed base filter throws a `ParseException` on every call. It never falls back to matching all documents.

## Controlling tool output

`search_index`, `sample_documents`, and `get_documents` return every source field by default. For an index with large fields, such as a full judgment body, ten hits can fill the agent's context. The `fields` argument and `toolFields()` keep results small, and the `matches` argument returns only the passages that matched.

The agent passes `fields`, a comma-separated list of source fields. Dotted paths reach nested fields:

```json
{"query": "burglary", "fields": "case_number,court_name,chunks.text"}
```

Override `toolFields()` to set the default the tools use when the agent passes `fields: null`. An empty list, the default, returns every field:

```php
class JudgmentIndex extends SigmieIndex
{
    use AsTool;

    public function properties(): NewProperties
    {
        $props = new NewProperties;
        $props->keyword('case_number');
        $props->category('court_name');
        $props->date('decision_date');
        $props->longText('text');                // full judgment body
        $props->nested('chunks', function (NewProperties $props) {
            $props->keyword('type');
            $props->text('text');                // one passage
        });

        return $props;
    }

    public function toolFields(): array
    {
        return ['case_number', 'court_name', 'decision_date']; // [tl! highlight]
    }
}
```

The agent can still request a field outside the default, such as `fields: "text"`. The tool descriptions name the default, so the agent knows what it gets. [Private fields](#private-fields) always win: a field from `exceptFromTools()` never returns, even when the agent requests it.

### Matching passages

A search for "burglary" matches the judgment, but the hit alone does not say which of its `chunks` matched. To cite a passage, the agent needs those chunks. The `matches` argument returns them. It lists the nested paths whose matching items the agent wants back. This is Elasticsearch inner hits, explained in [Search](search.md#matching-nested-items-inner-hits).

The agent calls `search_index` with `matches`. `fields` stays at the index default, so the full body and the chunk array stay out:

```json
{"query": "burglary", "fields": null, "matches": "chunks.text"}
```

Each hit gets `_matches`, next to the source fields:

```json
{
    "total": 1,
    "hits": [
        {
            "_id": "case-1",
            "case_number": "B 100-24",
            "court_name": "Stockholm",
            "decision_date": "2024-05-02",
            "_matches": {
                "chunks": {
                    "total": 4,
                    "items": [
                        {"_offset": 1, "text": "The burglary was proven."},
                        {"_offset": 4, "text": "A burglary witness testified."},
                        {"_offset": 2, "text": "The burglary tools were found."},
                        {"_offset": 3, "text": "Burglary sentence of one year."}
                    ]
                }
            }
        }
    ]
}
```

| Key | Holds |
|-----|-------|
| `_matches.chunks.total` | How many chunks of this judgment matched the query or a nested filter. |
| `_matches.chunks.items` | The matching chunks, best match first, up to 100. |
| `_offset` | The chunk's position in the `chunks` array. |

`matches: "chunks"` returns whole chunks, and `matches: "chunks.text chunks.type"` returns those fields of each chunk. Entries are separated by spaces or commas.

Add `:size` to an entry to cap that path's items, the same way `facets` takes `field:size`. Each path has its own size, from 1 to 100, and the default is 100:

```json
{"query": "burglary", "fields": null, "matches": "chunks.text:2 participants"}
```

Here `_matches.chunks` holds 2 of the 4 matching chunks with `total: 4`, and `_matches.participants` holds every matching participant. An invalid size, such as `chunks.text:0` or `chunks.text:500`, returns an error the agent can correct from:

```json
{"error": "Invalid matches entry 'chunks.text:0'. Use a nested path, optionally with ':size' from 1 to 100, e.g. 'chunks.text:5'. Check the field names and the filter/sort syntax in this tool's description, then try again."}
```

A nested filter such as `chunks:{type:'sentencing'}` also produces matches, and a chunk that matched both the query and the filter appears once. With `matches: null`, the default, hits have no `_matches` key. [Private fields](#private-fields) never appear in matches.

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
| `fields` | string | Comma-separated source fields to return. `null` uses `toolFields()`. |
| `matches` | string | Nested paths whose matching items return in `_matches`, separated by spaces or commas, each optionally `path:size` (1-100, default 100). `null` returns none. |

`sample_documents` and `get_documents` accept the same `fields` parameter.

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
