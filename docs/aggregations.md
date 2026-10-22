---
title: Aggregations
short_description: Reference for Sigmie's raw Elasticsearch aggregations. Metrics, terms, ranges, date histograms, geohash grids, sub-aggregations, and pipeline aggregations.
keywords: [aggregations, facets, analytics, bucket aggregations, union terms, metrics]
category: Features
order: 2
related_pages: [facets, analytics, query]
---

# Aggregations

`aggregate()` on a query adds Elasticsearch aggregations to the request. Each method on the `Aggs` builder adds one aggregation and returns it, so you can configure it further.

For filter sidebars, use [Facets](facets.md). For dashboards built from KPIs, trends, and breakdowns, use [Analytics](analytics.md).

## Run aggregations

Pass a callback to `aggregate()`. Read each result from the response with `aggregation()` and a dot path that starts with the aggregation name:

```php
use Sigmie\Query\Aggs;

$response = $sigmie->newQuery('orders')
    ->matchAll()
    ->aggregate(function (Aggs $agg) { // [tl! highlight]
        $agg->sum(name: 'turnover', field: 'price');
    })
    ->get();

$response->aggregation('turnover.value'); // 54.403
```

To return only the aggregations and no hits, set `size(0)`:

```php
$sigmie->newQuery('sales')
    ->matchAll()
    ->aggregate(function (Aggs $agg) {
        $agg->terms('top_products', 'product_id')->size(10);
    })
    ->size(0) // [tl! highlight]
    ->get();
```

To aggregate over a subset of documents, replace `matchAll()` with a query. See [Advanced Queries](query.md).

## Metric aggregations

A metric aggregation computes values over the documents in its scope.

| Method | Elasticsearch type | Result |
|--------|--------------------|--------|
| `sum(name, field)` | `sum` | `value` |
| `avg(name, field)` | `avg` | `value` |
| `min(name, field)` | `min` | `value` |
| `max(name, field)` | `max` | `value` |
| `stats(name, field)` | `stats` | `count`, `min`, `max`, `avg`, `sum` |
| `valueCount(name, field)` | `value_count` | `value`: the number of values, duplicates included |
| `cardinality(name, field)` | `cardinality` | `value`: the approximate number of distinct values |
| `percentiles(name, field, percents)` | `percentiles` | `values` keyed by percent. `percents` defaults to `[1, 5, 25, 50, 75, 95, 99]` |
| `percentileRanks(name, field, values)` | `percentile_ranks` | `values` keyed by value |
| `rate(name, field)` | `rate` | `value`. Only inside a date histogram |
| `topHits(name, size, sourceIncludes, sort, sourceExcludes)` | `top_hits` | `hits`. `size` defaults to 1 |
| `scriptedMetric(name, initScript, mapScript, combineScript, reduceScript, params)` | `scripted_metric` | `value` |

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

`cardinality()` accepts `precisionThreshold()`. Below the threshold, counts are close to exact. Above it, they are estimates:

```php
$agg->cardinality(name: 'unique_users', field: 'user_id')->precisionThreshold(1000);
```

## Bucket aggregations

A bucket aggregation groups documents. Each bucket has a `key` and a `doc_count`, plus the results of its [sub-aggregations](#sub-aggregations).

| Method | Elasticsearch type |
|--------|--------------------|
| `terms(name, field)` | `terms` |
| `multiTerms(name, fields)` | `multi_terms` |
| `unionTerms(name, fields)` | `terms` with a script |
| `range(name, field, ranges)` | `range` |
| `histogram(name, field, interval, minDocCount, extendedBounds)` | `histogram` |
| `dateHistogram(name, field, interval, minDocCount, extendedBounds, format, timeZone)` | `date_histogram` |
| `autoDateHistogram(name, field, buckets, minimumInterval, timeZone)` | `auto_date_histogram` |
| `geoHashGrid(name, field, precision, size)` | `geohash_grid` |
| `significantText(name, field)` | `significant_text` |
| `composite(name, sources, size, after)` | `composite` |
| `missing(name, field)` | `missing` |
| `global(name)` | `global` |
| `nested(name, path, callable)` | `nested` |
| `filter(name, query)` | `filter` |
| `rangeFilter(name, field, ranges)` | `filter` with a `range` query |

### Terms

`terms()` groups documents by the values of a field. Use a `keyword` field, or a `text` field with a keyword sub-field.

```php
$agg->terms(name: 'category_terms', field: 'category')
    ->size(10)
    ->missing('N/A');

$response->aggregation('category_terms.buckets');
// [
//     ['key' => 'Fantasy', 'doc_count' => 20],
//     ['key' => 'Musical', 'doc_count' => 18],
//     ['key' => 'Adventure', 'doc_count' => 13],
//     ['key' => 'N/A', 'doc_count' => 7],
// ]
```

| Method | Effect |
|--------|--------|
| `size(int)` | Number of buckets to return. Elasticsearch defaults to 10. |
| `order(string $key, string $direction)` | Sort buckets by `_count`, `_key`, or a sub-aggregation name. |
| `missing(value)` | Put documents without the field into a bucket with this key. |
| `exclude(array\|string)` | Leave these values out. |

### Union terms

`unionTerms()` counts one label across several fields as one terms aggregation. Use it when one dimension lives in fields such as `champion_country` and `runner_up_country`:

```php
use Sigmie\Query\Aggregations\Metrics\Sum;
use Sigmie\Query\Aggs;

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

A fixed Painless script reads the field names as parameters. A document with the same label in several of the fields counts once for that label. The [`unionBreakdown()`](analytics.md#union-breakdown) widget adds mapping validation, time windows, filters, and normalized rows.

### Range

`range()` groups a numeric field into the ranges you give. `from` is inclusive and `to` is exclusive:

```php
$agg->range('price_ranges', 'price', [
    ['key' => '0-100', 'to' => 100],
    ['key' => '100-200', 'from' => 100, 'to' => 200],
    ['key' => '200+', 'from' => 200],
]);

$response->aggregation('price_ranges.buckets');
// [
//     ['key' => '0-100', 'to' => 100.0, 'doc_count' => 803],
//     ['key' => '100-200', 'from' => 100.0, 'to' => 200.0, 'doc_count' => 422],
//     ['key' => '200+', 'from' => 200.0, 'doc_count' => 343],
// ]
```

### Histogram

`histogram()` groups a numeric field into buckets of a fixed width. `minDocCount` defaults to `0`, so empty buckets are included:

```php
$agg->histogram(name: 'price_histogram', field: 'price', interval: 50);
```

### Date histogram

`dateHistogram()` groups a date field by time. A `CalendarInterval` case sends a calendar interval. A string such as `'15d'` sends a fixed interval:

```php
use Sigmie\Query\Aggregations\Enums\CalendarInterval;

$agg->dateHistogram(name: 'sales_over_time', field: 'created_at', interval: CalendarInterval::Month); // [tl! highlight]
$agg->dateHistogram(name: 'fortnights', field: 'created_at', interval: '15d');
```

The `CalendarInterval` cases are `Minute`, `Hour`, `Day`, `Week`, `Month`, `Quarter`, and `Year`. `format` sets the format of each bucket's `key_as_string`, for example `'yyyy-MM'`.

### Auto date histogram

`autoDateHistogram()` lets Elasticsearch pick the interval that fits the number of buckets you ask for:

```php
$agg->autoDateHistogram(name: 'timeline', field: 'created_at', buckets: 12);
```

### Geohash grid

`geoHashGrid()` groups a `geo_point` field into geohash cells, for example to draw a map heatmap. `precision` goes from 1 to 12 and defaults to 5. A higher precision gives smaller cells. `size` caps the number of cells:

```php
$agg->geoHashGrid(name: 'areas', field: 'location', precision: 5);
```

## Sub-aggregations

Call `aggregate()` on a bucket aggregation to compute more aggregations inside each bucket:

```php
$agg->terms(name: 'category_terms', field: 'category')
    ->aggregate(function (Aggs $sub) { // [tl! highlight]
        $sub->avg(name: 'avg_price', field: 'price');
        $sub->max(name: 'max_price', field: 'price');
    });

$response->aggregation('category_terms.buckets.0.avg_price.value');
```

Each category bucket holds `avg_price` and `max_price` next to `doc_count`.

## Filtered aggregations

`rangeFilter()` limits its sub-aggregations to documents in a range. Keys are range operators:

```php
$agg->rangeFilter('expensive_products', 'price', ['>=' => 100])
    ->aggregate(function (Aggs $sub) {
        $sub->terms(name: 'expensive_categories', field: 'category');
    });
```

`filter()` takes any query object, for example `new Term('status', 'active')` from `Sigmie\Query\Queries\Term\Term`.

## Pipeline aggregations

A pipeline aggregation reads the output of other aggregations through a `buckets_path`. Add one with `add()`:

```php
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Aggregations\Pipeline\AvgBucket;

$agg->dateHistogram('sales_per_month', 'created_at', CalendarInterval::Month)
    ->aggregate(function (Aggs $sub) {
        $sub->sum('total_sales', 'amount');
    });

$agg->add(new AvgBucket('avg_monthly_sales', 'sales_per_month>total_sales')); // [tl! highlight]

$response->aggregation('avg_monthly_sales.value');
```

| Class in `Sigmie\Query\Aggregations\Pipeline` | Elasticsearch type | Place it |
|-----------------------------------------------|--------------------|----------|
| `AvgBucket(name, path)` | `avg_bucket` | Next to the bucket aggregation |
| `SumBucket(name, path)` | `sum_bucket` | Next to the bucket aggregation |
| `MinBucket(name, path)` | `min_bucket` | Next to the bucket aggregation |
| `MaxBucket(name, path)` | `max_bucket` | Next to the bucket aggregation |
| `CumulativeSum(name, path)` | `cumulative_sum` | Inside a histogram. Also `$sub->cumulativeSum(name, path)` |
| `SortBucket(name, path, order)` | `bucket_sort` | Inside the bucket aggregation |

`SortBucket` sorts the buckets of its parent by a sub-aggregation:

```php
use Sigmie\Query\Aggregations\Pipeline\SortBucket;

$agg->terms('products', 'product')
    ->aggregate(function (Aggs $sub) {
        $sub->sum('revenue', 'amount');
        $sub->add(new SortBucket('sort_by_revenue', 'revenue', 'desc'));
    });
```

## Missing values

`missing()` sets the value used for documents that do not have the field. Terms, date histogram, and auto date histogram aggregations accept it, and so do the `avg`, `min`, `max`, `sum`, `stats`, `cardinality`, `percentiles`, and `percentile_ranks` metrics:

```php
$agg->dateHistogram('histogram', 'date', CalendarInterval::Year)
    ->missing('2021-01-01');

$agg->max('count_max', 'count')->missing(0);
```
