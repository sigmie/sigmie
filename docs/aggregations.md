---
title: Aggregations
short_description: Run Elasticsearch metric and bucket aggregations with Sigmie — sum, avg, stats, terms, histogram, date histogram, geohash grid, and pipeline aggregations.
keywords: [aggregations, facets, analytics, bucket aggregations, union terms, metrics]
category: Features
order: 2
related_pages: [facets, search]
---

# Aggregations

Aggregations summarize and analyze your indexed data. Use them to power analytics dashboards, statistical summaries, and the underlying data for filter UIs.

Sigmie has two paths into aggregations:

1. **[Facets](facets.md)** — high-level, integrated with properties. The right choice for filter sidebars.
2. **Raw aggregations** — direct access to all Elasticsearch aggregation types. The right choice for analytics.

This page covers the raw aggregations API.

## Basic usage

```php
use Sigmie\Query\Aggs;

$response = $sigmie->newQuery('orders')
    ->matchAll()
    ->aggregate(function (Aggs $agg) {
        $agg->sum(name: 'turnover', field: 'price');
    })
    ->get();

$response->aggregation('turnover.value');     // 54.403
```

## Metric aggregations

Metrics return a single value across the matched documents.

### Sum

```php
$agg->sum(name: 'stock_sum', field: 'stock');
$response->aggregation('stock_sum.value');
```

SQL equivalent: `SELECT SUM(stock)`.

### Max / Min / Avg

```php
$agg->max(name: 'max_price', field: 'price');
$agg->min(name: 'min_price', field: 'price');
$agg->avg(name: 'avg_rating', field: 'rating');
```

Access with `$response->aggregation('max_price.value')`.

### Value count

Count of distinct values:

```php
$agg->valueCount(name: 'categories_count', field: 'category');
```

### Cardinality

Approximate distinct-value count — much cheaper than `valueCount` on large fields:

```php
$agg->cardinality(name: 'unique_users', field: 'user_id');
```

### Stats

A quick statistical summary:

```php
$agg->stats(name: 'sales_stats', field: 'amount');
$response->aggregation('sales_stats');
// [
//     'count' => 133,
//     'min'   => 5.33,
//     'max'   => 128.58,
//     'avg'   => 73.53,
//     'sum'   => 9779.49,
// ]
```

## Bucket aggregations

Bucket aggregations group documents by criteria — each bucket holds the documents that match.

### Terms

Group by the unique values of a field. Use a `keyword` field (or `text` field with a `.keyword` sub-field):

```php
$agg->terms(name: 'category_terms', field: 'category')->missing('N/A');

$response->aggregation('category_terms.buckets');
// [
//     ['key' => 'Musical', 'doc_count' => 18],
//     ['key' => 'Adventure', 'doc_count' => 13],
//     ['key' => 'Fantasy', 'doc_count' => 20],
//     ['key' => 'N/A', 'doc_count' => 7],
// ]
```

`missing('N/A')` puts documents without the field into a bucket of that key.

### Union terms

`unionTerms()` combines the same label from several role fields into one terms bucket. This is useful when one logical dimension is stored in fields such as `champion_country` and `runner_up_country`:

```php
use Sigmie\Query\Aggs;
use Sigmie\Query\Aggregations\Metrics\Sum;

$agg->unionTerms('countries', ['champion_country', 'runner_up_country'])
    ->size(10)
    ->order('prize_total', 'desc')
    ->aggregate(fn (Aggs $sub): Sum => $sub->sum('prize_total', 'prize'));

$response->aggregation('countries.buckets');
// [
//     ['key' => 'Germany', 'doc_count' => 4, 'prize_total' => ['value' => 420.0]],
//     ...
// ]
```

Field names are passed to a fixed aggregation script as parameters. A document that contains the same label in multiple configured fields contributes to that label once. Use the higher-level [`unionBreakdown()`](analytics.md#union-breakdown) API when you also want mapping validation, time windows, filters, and normalized rows.

### Range

Group by explicit numeric ranges:

```php
$agg->range(name: 'price_ranges', field: 'price', [
    ['key' => '0-100', 'to' => 100],
    ['key' => '100-200', 'from' => 100, 'to' => 200],
    ['key' => '200+', 'from' => 200],
]);

$response->aggregation('price_ranges.buckets');
// [
//     '0-100'   => ['to' => 100, 'doc_count' => 803],
//     '100-200' => ['from' => 100, 'to' => 200, 'doc_count' => 422],
//     '200+'    => ['from' => 200, 'doc_count' => 343],
// ]
```

### Histogram

Fixed-width buckets across a numeric field:

```php
$agg->histogram(name: 'price_histogram', field: 'price', interval: 50);
```

### Date histogram

Time-bucket documents:

```php
$agg->dateHistogram(name: 'sales_over_time', field: 'created_at', interval: 'month');
```

### Auto date histogram

Let Elasticsearch pick the bucket interval:

```php
$agg->autoDateHistogram(name: 'timeline', field: 'created_at', buckets: 12);
```

### Geohash grid

Bucket a `geo_point` field into geohash cells — the basis of a map heatmap. Higher `precision` (1–12) means smaller, more granular cells; `size` caps the number of returned cells:

```php
$agg->geoHashGrid(name: 'areas', field: 'location', precision: 5);
```

## Sub-aggregations

Nest aggregations to compute metrics per bucket:

```php
$agg->terms(name: 'category_terms', field: 'category')
    ->subAggregation(function (Aggs $sub) {
        $sub->avg(name: 'avg_price', field: 'price');
        $sub->max(name: 'max_price', field: 'price');
    });
```

Each category bucket now carries `avg_price` and `max_price` alongside `doc_count`.

## Pipeline aggregations

Operate on the output of other aggregations:

```php
$agg->terms(name: 'monthly_sales', field: 'month')
    ->subAggregation(function (Aggs $sub) {
        $sub->sum(name: 'total_sales', field: 'amount');
    })
    ->pipelineAggregation(function (Aggs $pipe) {
        $pipe->avgBucket(name: 'avg_monthly_sales', bucketsPath: 'monthly_sales>total_sales');
    });
```

## Filtered aggregations

Run an aggregation over a filtered subset of the query results:

```php
$agg->filter(name: 'expensive_products', filter: ['range' => ['price' => ['gte' => 100]]])
    ->subAggregation(function (Aggs $sub) {
        $sub->terms(name: 'expensive_categories', field: 'category');
    });
```

## Combined with the query builder

```php
$response = $sigmie->newQuery('products')
    ->properties($props)
    ->matchAll()
    ->facets('category price:50')
    ->scriptScore(
        source: "Math.log(2 + doc['popularity'].value)",
        boostMode: 'replace',
    )
    ->get();

$hits = $response->json('hits.hits');
$facets = $response->json('facets');
$rawAggs = $response->json('aggregations');
```

## Analytics-only requests

For pure analytics (no documents needed), set `size(0)`:

```php
$response = $sigmie->newQuery('sales')
    ->matchAll()
    ->aggregate(function (Aggs $agg) {
        $agg->dateHistogram('sales_over_time', 'date', 'month')
            ->subAggregation(function (Aggs $sub) {
                $sub->sum('monthly_revenue', 'amount');
            });

        $agg->terms('top_products', 'product_id')
            ->size(10)
            ->subAggregation(function (Aggs $sub) {
                $sub->sum('product_revenue', 'amount');
            });
    })
    ->size(0)
    ->get();
```

## Performance

- Use `keyword` fields for term aggregations — `text` fields require `.keyword` sub-fields.
- Limit bucket size — `terms(...)->size(10)` for top 10.
- Aggregate inside a `filter()` boolean clause to enable Elasticsearch's filter cache.
- Cardinality aggregations on high-cardinality fields use significant memory.

```php
$sigmie->newQuery('products')
    ->properties($props)
    ->bool(function ($bool) {
        $bool->filter()->term('status', 'active');     // cached
        $bool->must()->match('title', $searchTerm);
    })
    ->facets('category:10 brand:10')                   // top 10 per facet
    ->size(20)
    ->get();
```

## See also

- [Facets](facets.md) — high-level facets for filter UIs.
- [Mappings & Properties](mappings.md) — choosing the right field type for aggregation.
- [Advanced Queries](query.md) — combining aggregations with custom queries.
