---
title: Laravel AI SDK
short_description: Sigmie's Laravel AI SDK tools give an agent search, value discovery, samples, and analytics on an index, scoped by base filters and private fields.
keywords: [laravel ai, ai sdk, tools, agents, llm, private fields, tool output, inner hits]
category: Integrations
order: 2
related_pages: [search, filter-parser, sort-parser, facets, analytics, laravel-scout]
---

# Laravel AI SDK

Sigmie exposes an index as a set of [Laravel AI SDK](https://laravel.com/docs/ai-sdk) tools. An agent with these tools searches, filters, sorts, facets, and reads documents in the index. The tools build their descriptions from your property definitions. They require the `laravel/ai` package.

## Register the tools in an agent

Add the `AsTool` trait to a `SigmieIndex` subclass:

```php
use Sigmie\AI\AsTool;
use Sigmie\Mappings\NewProperties;
use Sigmie\SigmieIndex;

class ProductIndex extends SigmieIndex
{
    use AsTool; // [tl! highlight]

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

Return the index's `tools()` from the agent:

```php
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

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
            ...app(ProductIndex::class)->tools(), // [tl! highlight]
        ];
    }
}
```

To give the agent the search tool alone, construct it directly. This does not need the trait:

```php
use Sigmie\AI\SigmieIndexTool;

new SigmieIndexTool(app(ProductIndex::class));
```

## The tool suite

`tools(string $baseFilter = '')` returns six tools, in this order:

| # | Class | `name()` | Purpose |
|---|-------|----------|---------|
| 0 | `SigmieIndexTool` | not defined | Query, filter, sort, facet, and paginate. |
| 1 | `SigmieFilterValuesTool` | `discover_filter_values` | List the values of one facetable field. |
| 2 | `SigmieSampleDocumentsTool` | `sample_documents` | Return random documents, so the agent sees the data shape. |
| 3 | `SigmieGetDocumentsTool` | `get_documents` | Return documents by `_id`. |
| 4 | `SigmieIndexSchemaTool` | `describe_index` | Return the field list, types, and filter syntax as structured data. |
| 5 | `SigmieAnalyticsTool` | `analytics` | Compute one dashboard widget per call. See [Analytics](analytics.md). |

The tool descriptions refer to the search tool as `search_index`.

## Base filters

A base filter limits every query the agent runs. Use it for multi-tenancy or per-user access. The agent does not choose the base filter and does not see it in any tool description.

```php
app(OrderIndex::class)->tools("user_id:{$user->id}"); // [tl! highlight]

new SigmieIndexTool(app(OrderIndex::class), baseFilters: "user_id:{$user->id}");
```

Sigmie parses the base filter on its own and adds it as a separate filter clause. It parses the agent's `filters` string on its own too, so the two strings never join as text:

```
base filter:  user_id:3                              -> hard filter clause
agent filter: status:'shipped' OR status:'delivered' -> second clause
match:        documents that pass both clauses
```

An agent filter with unbalanced parentheses, such as `status:'x') OR (user_id:4`, cannot form an `OR` that escapes the scope. The tool rejects it. `handle()` returns an `{"error": ...}` the agent can correct from, and `result()` throws a `ParseException`.

Every tool that reads documents applies the base filter, including `sample_documents` and `get_documents`. `describe_index` reads only the mapping, so it takes no base filter. Sampling draws only from in-scope documents. An out-of-scope id gives the same result as a missing id:

```php
use Laravel\Ai\Tools\Request;

[, , $sample, $get] = app(OrderIndex::class)->tools('user_id:3');

$get->handle(new Request(['ids' => ['order-of-user-3', 'order-of-user-4']]));
// [{"_id": "order-of-user-3", ...}]  (order-of-user-4 is omitted, like a missing id)
```

> **Warning:** A malformed base filter throws a `ParseException` on every call. It never falls back to matching all documents.

## Output fields

`SigmieIndexTool`, `sample_documents`, and `get_documents` return every source field by default. For an index with large fields, such as a full article body, ten hits can fill the agent's context.

The agent passes `fields`, a comma-separated list of source fields. Dotted paths reach nested fields:

```json
{"query": "solar", "fields": "title,author,sections.text"}
```

Override `toolFields()` to set the fields the tools return when the agent passes `fields: null`. An empty list, the default, returns every field:

```php
class ArticleIndex extends SigmieIndex
{
    use AsTool;

    public function properties(): NewProperties
    {
        $props = new NewProperties;
        $props->title('title');
        $props->category('author');
        $props->date('published_at');
        $props->longText('body');                // full article body
        $props->nested('sections', function (NewProperties $props) {
            $props->keyword('type');
            $props->text('text');                // one section
        });
        $props->nested('comments', function (NewProperties $props) {
            $props->text('text');
        });

        return $props;
    }

    public function toolFields(): array
    {
        return ['title', 'author', 'published_at']; // [tl! highlight]
    }
}
```

The agent can still request a field outside the default, such as `fields: "body"`. The `fields` parameter description names the default, so the agent knows what it gets. [Private fields](#private-fields) always win. A field from `exceptFromTools()` never returns, even when the agent requests it.

## Matching nested items

A search for "solar" matches the article, but the hit alone does not say which of its `sections` matched. The `matches` argument of `SigmieIndexTool` returns those sections. It lists the nested paths whose matching items the agent wants back. Sigmie runs these as Elasticsearch inner hits, described in [Search](search.md#matching-nested-items-inner-hits).

With `fields: null`, the index default above keeps the body and the full section array out:

```json
{"query": "solar", "fields": null, "matches": "sections.text"}
```

Each hit gets `_matches` next to its source fields:

```json
{
    "total": 1,
    "hits": [
        {
            "_id": "article-1",
            "title": "Energy at home",
            "author": "Jane Doe",
            "published_at": "2024-05-02",
            "_matches": {
                "sections": {
                    "total": 4,
                    "items": [
                        {"_offset": 1, "text": "The solar panels were installed."},
                        {"_offset": 2, "text": "The solar inverter was replaced."},
                        {"_offset": 4, "text": "A solar battery stores the surplus."},
                        {"_offset": 3, "text": "Solar power pays off in six years."}
                    ]
                }
            }
        }
    ]
}
```

| Key | Holds |
|-----|-------|
| `_matches.sections.total` | How many sections of this article matched the query or a nested filter. |
| `_matches.sections.items` | The matching sections, best match first, up to 100. |
| `_offset` | The section's position in the `sections` array. |

`matches: "sections"` returns whole sections. `matches: "sections.text sections.type"` returns those fields of each section. Spaces or commas separate entries.

Add `:size` to an entry to cap that path's items, the same way `facets` takes `field:size`. Each path has its own size, from 1 to 100. The default is 100:

```json
{"query": "solar", "fields": null, "matches": "sections.text:2 comments"}
```

Here `_matches.sections` holds 2 of the 4 matching sections with `total: 4`, and `_matches.comments` holds every matching comment. An invalid size, such as `sections.text:0` or `sections.text:500`, returns an error the agent can correct from:

```json
{"error": "Invalid matches entry 'sections.text:0'. Use a nested path, optionally with ':size' from 1 to 100, e.g. 'sections.text:5'. Check the field names and the filter/sort syntax in this tool's description, then try again."}
```

A nested filter such as `sections:{type:'conclusion'}` also produces matches. A section that matches both the query and the filter appears once. With `matches: null`, the default, hits have no `_matches` key. [Private fields](#private-fields) never appear in matches.

## Private fields

Override `exceptFromTools()` to keep fields out of every document the tools return. Dotted paths reach nested fields:

```php
class CustomerIndex extends SigmieIndex
{
    use AsTool;

    public function properties(): NewProperties
    {
        $props = new NewProperties;
        $props->name('name');
        $props->nested('contacts', function (NewProperties $props) {
            $props->keyword('name');
            $props->keyword('email');
        });

        return $props;
    }

    public function exceptFromTools(): array
    {
        return ['contacts.email']; // [tl! highlight]
    }
}
```

`SigmieIndexTool`, `sample_documents`, `get_documents`, and the `analytics` tool's `table` widget and `include_hits` rows omit these fields. Elasticsearch drops them from `_source` before the response leaves the cluster. The exclusion wins over the agent's own `fields` and `hit_fields`, so `hit_fields: "contacts"` returns `contacts.name` only.

The agent can still filter on a private field:

```
contacts:{email:'jane@example.com'}
```

Every tool refuses to list, facet, group, measure, or sort by a private field or any field below it. This covers `discover_filter_values`, the search `facets` and `sort`, and every `analytics` argument that names a field, such as `group_by`, `group_by_fields`, `row_field`, `field`, `sort`, and `hit_sort`. `handle()` returns an error the agent can correct from, and `result()` throws an `InvalidArgumentException`:

```json
{"error": "Field contacts.email is private and cannot be listed, grouped, faceted or sorted; you can still filter on it. ..."}
```

The tool descriptions and `describe_index` mark these fields as filter only, so the agent does not try. Your own code keeps full access. `facets()` on a search and `analytics()` on the index ignore `exceptFromTools()`.

## Search tool description

`SigmieIndexTool` builds its description from the index properties. For these properties:

```php
$props->keyword('brand');
$props->number('price');
$props->bool('in_stock');
$props->date('created_at');
```

The agent sees this description. The last lines are shortened here:

```
Search the 'products' index.

Available fields:
- brand [keyword] (sortable, facetable): brand:'value' brand:['a','b'] brand:val*
- price [number] (sortable, facetable): price>n price<=n price:min..max
- in_stock [boolean] (sortable): in_stock:true in_stock:false
- created_at [date] (sortable): created_at>'2024-01-01' created_at<'2024-12-31'

Filter operators: AND, OR, AND NOT
Negation: NOT field:'value'
Grouping: (field:'a' OR field:'b') AND other>10
Exists check: field:*
Sort: space-separated list of 'field:asc' or 'field:desc' ...
Geo sort: field[lat,lon]:km:asc
Facets: field1 field2:20 (space-separated, optional :size for keywords or :interval for numbers)
Matching: equality filters on text/keyword fields are exact and CASE-SENSITIVE ...
Output: each hit holds `_id` plus the source fields in `fields` (null uses the index default).
Matches: to see WHICH items of a nested field matched the query or a nested filter, ...
Discovering valid values: if you do not know a field's valid values, call discover_filter_values ...
Schema: call describe_index for the full structured field list, types and filter syntax.
```

A field's `description` from the mapping follows its line. A private field shows `(filter only)` instead of its capabilities. The full syntax lives in [Filter parser](filter-parser.md), [Sort parser](sort-parser.md), and [Facets](facets.md).

## Search tool parameters

Every parameter except `query` is nullable and listed as required, so the schema is valid under OpenAI strict function calling. The agent passes `null` for a parameter it does not use.

| Parameter | Type | Description |
|-----------|------|-------------|
| `query` | string | Search query text. |
| `filters` | string | Filter expression. See [Filter parser](filter-parser.md). |
| `sort` | string | Sort expression. See [Sort parser](sort-parser.md). |
| `facets` | string | Space-separated facet fields. See [Facets](facets.md). |
| `facet_filters` | string | Active facet filter values. |
| `per_page` | int | Results per page. Default 10. |
| `page` | int | Page number. Default 1. |
| `fields` | string | Comma-separated source fields to return. `null` uses `toolFields()`. |
| `matches` | string | Nested paths whose matching items return in `_matches`. Spaces or commas separate entries, each optionally `path:size` (1 to 100, default 100). `null` returns none. |

The result holds `total` and `hits`. Each hit holds `_id`, the selected source fields, and `_matches` when requested. When the agent requests facets, the result also holds a `facets` key with the facet data.

## Companion tool parameters

| Tool | Parameter | Description |
|------|-----------|-------------|
| `discover_filter_values` | `field` | Facetable field to list values for. Required. |
| | `filters` | Filter expression that narrows the values. |
| | `limit` | Maximum values to return. Default 20. |
| `sample_documents` | `limit` | Number of documents, 1 to 20. Default 5. |
| | `fields` | Same as the search tool's `fields`. |
| `get_documents` | `ids` | Document ids, up to 100. Required. Results keep the order of `ids`. |
| | `fields` | Same as the search tool's `fields`. |
| `describe_index` | none | Returns `index`, `fields`, `filter_syntax`, and `notes`. |

For the `analytics` widgets and their arguments, see [Analytics](analytics.md).

## Discovered values

`discover_filter_values` returns at most `limit` values. When more values exist, `truncated` is `true` and `other_documents` counts the documents in the values left out:

```json
{
    "field": "color",
    "values": { "red": 12, "blue": 7 },
    "truncated": true, // [tl! highlight]
    "other_documents": 5
}
```

Here `limit` was 2, and 5 more documents have other colors. The tool description tells the agent to raise `limit` or narrow the list with `filters`, such as `"color:b*"`, before it treats the list as complete.

Numeric and date fields return min and max, and always report `truncated: false`. A date field returns the period it covers as date strings, and `filters` narrow that period:

```json
{
    "field": "published_at",
    "values": { "count": 3, "min": "2023-01-04T00:00:00.000Z", "max": "2025-11-30T00:00:00.000Z" }, // [tl! highlight]
    "truncated": false,
    "other_documents": 0
}
```

## Errors

The tools parse the agent's `filters`, `facets`, and `facet_filters` strictly. An unknown field or an expression that does not parse returns an `error` result the agent can read and correct. It does not return an empty result:

```
filters: color:'red'    (no "color" field)

result: {"error": "Field color does not exist. Check the field names and the filter/sort syntax in this tool's description, then try again."}
```

`discover_filter_values` applies the same rule to its `field` and `filters`.

Only the agent-facing `handle()` returns errors as JSON. The `result()` methods return arrays and throw, so your own code keeps normal exception handling:

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

- [Filter parser](filter-parser.md) for every filter operator.
- [Sort parser](sort-parser.md) for sort expression syntax.
- [Facets](facets.md) for facet behavior and structure.
- [Analytics](analytics.md) for the widgets behind the `analytics` tool.
- [Search](search.md) for the search builder the tools use.
- [MCP server](mcp.md) to connect a coding assistant to the Sigmie documentation.
