---
title: Analytics
short_description: Sigmie analytics builds Elasticsearch dashboards from KPIs, trends, ranked breakdowns, funnels, cohorts, and tables, and runs every widget in one request.
keywords: [analytics, dashboard, trends, KPI, time series, breakdown, union breakdown, histogram, percentiles, funnel, retention, cohort, heatmap, geo, table, multi-search, AI agent]
category: Features
order: 3
related_pages: [aggregations, facets, laravel-ai]
---

# Analytics

`analytics()` builds a dashboard from named **widgets**, such as KPIs, trends, ranked lists, and funnels. `get()` runs every widget in one Elasticsearch request. For anything no widget covers, use [raw aggregations](aggregations.md).

```php
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;

$dashboard = $orders->analytics('created_at')
    ->from(new DateTimeImmutable('-30 days'))
    ->to(new DateTimeImmutable)
    ->filters("status:'paid'")
    ->kpiDelta('revenue', Metric::Sum, 'amount')
    ->trend('revenue_over_time', Metric::Sum, 'amount', CalendarInterval::Day)
    ->breakdown('top_products', 'product', Metric::Sum, 'amount', limit: 5)
    ->get();
```

`analytics()` is a method of every [`SigmieIndex`](mappings.md). Its argument is the **timeline**, the date field that every widget filters and buckets by. The window defaults to the last 30 days.

`$sigmie->analytics($indexName, $dateField)` starts the same builder without an index class. Call `properties()` on it so that filters are typed and text fields group on their `.keyword` sub-field.

## Metrics

Widgets that measure something take a `Metric`:

| Metric           | Value                                                    |
| ---------------- | -------------------------------------------------------- |
| `Metric::Sum`    | Total of a numeric field                                 |
| `Metric::Avg`    | Average of a numeric field                               |
| `Metric::Min`    | Minimum value                                            |
| `Metric::Max`    | Maximum value                                            |
| `Metric::Count`  | Number of documents with a value in the field            |
| `Metric::Unique` | Distinct values. Approximate above 40,000 distinct values |
| `Metric::Median` | 50th percentile                                          |

When you give `Metric::Count` no field, it counts the timeline field, so it counts every document in the window.

## Widgets

`get()` returns an array keyed by widget name. Each value has a `type` key and the chart data. The outputs below are shortened.

Every widget method also takes [`filter:`](#per-widget-filter) and [`window:`](#per-widget-window) as its last arguments.

### KPI

`kpi()` returns one number for the window.

```php
$orders->analytics('created_at')
    ->kpi('revenue', Metric::Sum, 'amount')
    ->kpi('customers', Metric::Unique, 'customer_id')
    ->get();

// ['revenue' => ['type' => 'kpi', 'value' => 48210.0, 'count' => 1240, ...], ...]
```

### KPI delta

`kpiDelta()` compares the window with the previous window of equal length. [Calendar periods](#named-ranges) compare with the previous calendar period. `change_pct` is `null` when the previous value is `0` or missing.

```php
$orders->analytics('created_at')
    ->from($monthStart)->to($monthEnd)
    ->kpiDelta('revenue', Metric::Sum, 'amount')
    ->get();

// ['revenue' => ['type' => 'kpi_delta', 'value' => 300.0, 'previous' => 150.0, 'change_pct' => 100.0, ...]]
```

### Trend

`trend()` returns a metric per time bucket. To change from daily to monthly buckets, change only the `CalendarInterval`. The series spans the whole window, including empty buckets.

```php
$orders->analytics('created_at')
    ->trend('revenue', Metric::Sum, 'amount', CalendarInterval::Day)
    ->get();

// ['revenue' => ['type' => 'trend', 'interval' => 'Day', 'series' => [
//     ['label' => '2024-01-01T00:00:00.000Z', 'value' => 150.0], ...
// ]]]
```

Intervals: `Minute`, `Hour`, `Day`, `Week`, `Month`, `Quarter`, and `Year`.

### Grouped trend

`groupedTrend()` returns one series for each of the top `limit` values of `groupBy`, such as revenue by product over time.

```php
$orders->analytics('created_at')
    ->groupedTrend('by_product', Metric::Sum, 'amount', groupBy: 'product', interval: CalendarInterval::Day, limit: 5)
    ->get();

// ['by_product' => ['type' => 'grouped_trend', 'groups' => [
//     ['group' => 'A', 'series' => [['label' => ..., 'value' => 370.0], ...]], ...
// ]]]
```

The `field` argument is required. To count events, pass an empty string:

```php
$events->analytics('occurred_at')
    ->groupedTrend(
        'events_by_type',
        Metric::Count,
        field: '',
        groupBy: 'event_type',
        interval: CalendarInterval::Day,
    )
    ->get();
```

### Breakdown

`breakdown()` returns the top `limit` values of a field, ranked by a metric. `direction` defaults to `'desc'`. Pass `'asc'` to return the lowest values first.

```php
$orders->analytics('created_at')
    ->breakdown('top_products', 'product', Metric::Sum, 'amount', limit: 10)
    ->breakdown('smallest_queues', 'queue', Metric::Count, limit: 5, direction: 'asc')
    ->get();

// ['top_products' => ['type' => 'breakdown', 'rows' => [
//     ['key' => 'A', 'value' => 370.0, 'count' => 3, ...], ...
// ]], ...]
```

### Multi breakdown

`multiBreakdown()` ranks combinations of fields, such as product and channel pairs.

```php
$orders->analytics('created_at')
    ->multiBreakdown(
        'top_product_channels',
        ['product', 'channel'],
        Metric::Sum,
        field: 'amount',
        limit: 10,
    )
    ->get();

// ['top_product_channels' => ['type' => 'multi_breakdown', 'rows' => [
//     ['key' => ['product' => 'A', 'channel' => 'online'],
//      'key_values' => ['A', 'online'], 'label' => 'A / online',
//      'value' => 240.0, 'count' => 2], ...
// ]]]
```

### Union breakdown

`unionBreakdown()` ranks labels across several fields. A country in `champion_country` or in `runner_up_country` counts toward one country row.

```php
$tournaments->analytics('played_at')
    ->unionBreakdown(
        'country_appearances',
        ['champion_country', 'runner_up_country'],
        Metric::Count,
        limit: 10,
    )
    ->unionBreakdown(
        'country_prize',
        ['champion_country', 'runner_up_country'],
        Metric::Sum,
        field: 'prize',
        limit: 10,
    )
    ->get();

// ['country_appearances' => ['type' => 'union_breakdown', 'rows' => [
//     ['key' => 'Germany', 'value' => 4, 'count' => 4, ...], ...
// ]], ...]
```

The fields need compatible, aggregatable mapping types. A document with the same label in two fields counts once. `limit` and `direction` work as in `breakdown()`. See [union terms](aggregations.md#union-terms) for the aggregation.

### Grouped metrics

`groupedMetrics()` returns several metrics per group and ranks groups by the metric whose `key` is `sortMetric`. Metrics other than count need a numeric `field`. `minCount` drops smaller groups.

```php
$orders->analytics('created_at')
    ->groupedMetrics(
        'product_metrics',
        groupBy: 'product',
        metrics: [
            ['key' => 'orders', 'label' => 'Orders', 'metric' => Metric::Count],
            ['key' => 'average_amount', 'label' => 'Average amount', 'metric' => Metric::Avg, 'field' => 'amount'],
        ],
        sortMetric: 'orders',
        limit: 10,
        minCount: 2,
    )
    ->get();

// ['product_metrics' => ['type' => 'grouped_metrics', 'rows' => [
//     ['key' => 'A', 'value' => 3, 'count' => 3,
//      'metrics' => ['orders' => 3, 'average_amount' => 123.33],
//      'metric_populations' => [
//          'orders' => ['document_count' => 3, 'value_count' => 3, 'field' => ''],
//          'average_amount' => ['document_count' => 3, 'value_count' => 3, 'field' => 'amount'],
//      ]], ...
// ]]]
```

In `metric_populations`, `value_count` below `document_count` means some documents in the group have no value for that metric.

### Distribution

`distribution()` counts documents in fixed-width ranges of a numeric field.

```php
$orders->analytics('created_at')
    ->distribution('order_sizes', 'amount', interval: 100)
    ->get();

// ['order_sizes' => ['type' => 'distribution', 'buckets' => [['label' => 0, 'count' => 3], ['label' => 100, 'count' => 1], ...]]]
```

### Histogram metric

`histogramMetric()` computes a metric in each fixed-width range of `bucketField`, such as the average rating per price band.

```php
$products->analytics('created_at')
    ->histogramMetric(
        'average_rating_by_price',
        bucketField: 'price',
        interval: 50,
        metric: Metric::Avg,
        field: 'rating',
    )
    ->get();

// ['average_rating_by_price' => [
//     'type' => 'histogram_metric',
//     'bucket_field' => 'price',
//     'interval' => 50,
//     'series' => [
//         ['label' => 0, 'value' => 4.2, 'count' => 18],
//         ['label' => 50, 'value' => 4.6, 'count' => 7], ...
//     ],
// ]]
```

Each `label` is the lower bound of its range. `50` covers 50 up to, but not including, 100.

### Percentiles

`percentiles()` returns the given percentiles of a numeric field. The default is `[50, 75, 95, 99]`.

```php
$requests->analytics('created_at')
    ->percentiles('latency', 'duration_ms', [50, 95, 99])
    ->get();

// ['latency' => ['type' => 'percentiles', 'percentiles' => ['50' => 120.0, '95' => 880.0, '99' => 1450.0]]]
```

### Cumulative

`cumulative()` returns a running total per time bucket, from a `cumulative_sum` pipeline aggregation.

```php
$orders->analytics('created_at')
    ->cumulative('growth', Metric::Sum, 'amount', CalendarInterval::Day)
    ->get();

// ['growth' => ['type' => 'cumulative', 'series' => [
//     ['label' => '2024-01-01...', 'value' => 150.0],
//     ['label' => '2024-01-02...', 'value' => 350.0], ...
// ]]]
```

### Stats

`stats()` returns `count`, `min`, `max`, `avg`, and `sum` of a numeric field.

```php
$orders->analytics('created_at')
    ->stats('order_size', 'amount')
    ->get();

// ['order_size' => ['type' => 'stats', 'count' => 5, 'min' => 30.0, 'max' => 200.0, 'avg' => 90.0, 'sum' => 450.0]]
```

### Table

`table()` returns matching documents instead of a number. It uses a `top_hits` aggregation, so filters and windows apply as for other widgets.

| Argument | Default  | Effect                                                              |
| -------- | -------- | ------------------------------------------------------------------- |
| `fields` | `[]`     | Source fields to return. An empty array returns the whole document. |
| `limit`  | `10`     | Number of rows.                                                     |
| `sort`   | `null`   | A `"field:direction"` string. The direction defaults to `desc`.     |
| `except` | `[]`     | Source fields to remove, even when `fields` lists their parent.     |

```php
$orders->analytics('created_at')
    ->table('recent', fields: ['amount', 'product'], limit: 20, sort: 'amount:desc')
    ->get();

// ['recent' => ['type' => 'table', 'rows' => [
//     ['id' => 'ord_8f2c…', 'document' => ['amount' => 200, 'product' => 'A']], ...
// ]]]

$customers->analytics('created_at')
    ->table('recent', fields: ['name', 'contacts'], except: ['contacts.email'])
    ->get();
```

`table()` does not paginate. For a paginated table, use [`search()`](search.md) in the same request through [multi-search](#batch-with-multi-search).

### Funnel

`funnel()` counts the documents that match each step. `steps` is an ordered array of `label => slice`, where a slice is a [filter-parser](filter-parser.md) string or a query object. `conversion` compares each step with the first step, and `step_conversion` with the previous step.

```php
$events->analytics('created_at')
    ->funnel('signup', [
        'visited' => "event:'visit'",
        'signed'  => "event:'signup'",
        'paid'    => "event:'paid'",
    ])
    ->get();

// ['signup' => ['type' => 'funnel', 'steps' => [
//     ['label' => 'visited', 'count' => 4, 'conversion' => 1.0,  'step_conversion' => 1.0],
//     ['label' => 'signed',  'count' => 2, 'conversion' => 0.5,  'step_conversion' => 0.5],
//     ['label' => 'paid',    'count' => 1, 'conversion' => 0.25, 'step_conversion' => 0.5],
// ]]]
```

### Heatmap

`heatmap()` returns a `rowField` by `colField` matrix. Each cell holds a metric, by default a document count. `rowLimit` and `colLimit` default to `10`.

```php
$orders->analytics('created_at')
    ->heatmap('by_region', rowField: 'region', colField: 'device', metric: Metric::Sum, field: 'amount')
    ->get();

// ['by_region' => ['type' => 'heatmap', 'rows' => [
//     ['key' => 'US', 'count' => 3, 'cells' => [
//         ['key' => 'mobile', 'value' => 220.0, 'count' => 2],
//         ['key' => 'desktop', 'value' => 90.0, 'count' => 1],
//     ]], ...
// ]]]
```

### Retention

`retention()` returns a cohort table. It groups entities by the period of their `cohortField` date, then counts distinct `idField` values in each later timeline period. `size` is the cohort's distinct entity count.

```php
$signups->analytics('active_at')
    ->retention('cohorts', cohortField: 'signup_at', idField: 'user_id', interval: CalendarInterval::Week)
    ->get();

// ['cohorts' => ['type' => 'retention', 'cohorts' => [
//     ['cohort' => '2024-01-01...', 'size' => 2, 'periods' => [
//         ['label' => '2024-01-01...', 'value' => 2],
//         ['label' => '2024-01-08...', 'value' => 1],
//     ]], ...
// ]]]
```

### Geo

`geo()` groups documents into geohash cells of a `geo_point` field. A higher `precision` gives smaller cells. Each cell holds a document count unless you pass `metric:` and `metricField:`.

```php
$orders->analytics('created_at')
    ->geo('areas', 'location', precision: 5)
    ->get();

// ['areas' => ['type' => 'geo', 'precision' => 5, 'buckets' => [
//     ['geohash' => 'u173z', 'value' => 2, 'count' => 2], ...
// ]]]
```

## Filters

`filters()` applies a [filter-parser](filter-parser.md) string to every widget. `filterQuery()` adds a query object with AND, like [`NewSearch::filterQuery()`](search.md).

```php
use Sigmie\Query\Queries\Term\Range;

$orders->analytics('created_at')
    ->filters("status:'paid'")
    ->filterQuery(new Range('amount', ['>=' => 100]))
    ->kpi('big_orders', Metric::Sum, 'amount')
    ->get();
```

### Per-widget filter

The `filter:` argument limits one widget to a slice of documents, in addition to the dashboard filters. It takes a filter-parser string or a query object. On `kpiDelta()`, it applies to both windows.

```php
use Sigmie\Query\Queries\Term\Term;

$orders->analytics('created_at')
    ->from($start)->to($end)
    ->kpi('total', Metric::Unique, 'customer_id')
    ->kpi('engaged', Metric::Unique, 'customer_id', filter: "status:'opened' OR status:'completed'")
    ->kpi('completed', Metric::Unique, 'customer_id', filter: new Term('status', 'completed'))
    ->trend('paid_revenue', Metric::Sum, 'amount', CalendarInterval::Day, filter: "status:'paid'")
    ->kpiDelta('refunds', Metric::Count, filter: "status:'refunded'")
    ->get();
```

## Time window

`from()` and `to()` set the dashboard window. It includes `from` and excludes `to`. Create both dates in the user's timezone to align the window with the user's day.

### Per-widget window

The `window:` argument gives one widget its own time range. It takes a [`Period`](#named-ranges) or a `[$from, $to]` pair.

```php
use Sigmie\Analytics\Enums\Period;

$orders->analytics('created_at')
    ->from($last30Start)->to($now)
    ->kpi('lifetime_revenue', Metric::Sum, 'amount', window: [$accountStart, $now])
    ->kpi('this_year', Metric::Sum, 'amount', window: Period::ThisYear)
    ->trend('recent_revenue', Metric::Sum, 'amount', CalendarInterval::Day)
    ->get();
```

A `kpiDelta()` with its own `window:` always compares against the previous window of equal length.

## Named ranges

`range()` sets the window to a `Period`, converted to fixed dates in the configured timezone. Weeks start on Monday.

```php
use Sigmie\Analytics\Enums\Period;

$orders->analytics('created_at')->timezoneOffset(540)->range(Period::Last7Days);
```

Periods: `Today`, `Yesterday`, `ThisWeek`, `LastWeek`, `ThisMonth`, `LastMonth`, `ThisQuarter`, `LastQuarter`, `ThisYear`, `LastYear`, `Last7Days`, `Last30Days`, and `Last90Days`.

With a week, month, quarter, or year period, `kpiDelta()` compares with the previous calendar period: `ThisMonth` compares with last month. Other periods compare with the previous window of equal length.

> **Note:** `range()` resolves the period when you call it. Set the timezone first.

## Timezone

Analytics uses UTC by default, so a daily chart puts a Tokyo sale made just after midnight into the previous day. Set a timezone to align time buckets and named ranges with local time:

```php
$orders->analytics('created_at')
    ->timezoneOffset(540)
    ->range(Period::ThisMonth)
    ->trend('sales', Metric::Sum, 'amount', CalendarInterval::Day)
    ->get();
```

| Method                       | Argument                                                                               |
| ---------------------------- | -------------------------------------------------------------------------------------- |
| `timezoneOffset(int)`        | Minutes east of UTC: Tokyo is `540`, New York is `-300`, India is `330`, Nepal is `345`. |
| `timezone(string)`           | An IANA name such as `'Asia/Tokyo'`, or a fixed offset such as `'+09:00'`.              |

A fixed offset ignores daylight saving time. An IANA name follows it. In a browser, `-new Date().getTimezoneOffset()` gives the value for `timezoneOffset()`.

## Hits alongside widgets

`getWithHits()` returns the widgets and the matching documents from one request. The request has `size(0)` by default, so configure the hits with `search()`.

```php
$result = $orders->analytics('created_at')
    ->from($start)->to($end)
    ->kpi('revenue', Metric::Sum, 'amount')
    ->search(function ($search) {
        $search
            ->size(20)
            ->sortString('amount:desc')
            ->postFilterString("status:'paid'")
            ->trackTotalHits(true);
    })
    ->getWithHits();

$result['widgets']['revenue']; // normalized KPI result
$result['hits'];               // raw Elasticsearch hits block
```

The result also has `took` and `timed_out`. A `post_filter` limits the hits but not the widgets, so the KPI above counts all orders and the hits show paid orders.

## Batch with multi-search

When the rows need a different query, `newAnalytics()` adds the dashboard to a [multi-search](search.md). Its result is the widget array that `get()` returns.

```php
$multi = $sigmie->newMultiSearch();

$multi->newAnalytics($orders, 'created_at', 'metrics')
    ->from($start)->to($end)
    ->kpiDelta('revenue', Metric::Sum, 'amount')
    ->trend('revenue_over_time', Metric::Sum, 'amount', CalendarInterval::Day);

$multi->newSearch($orders->name(), 'rows')
    ->queryString('')->filters("status:'paid'")->page(3, 20);

[$metrics, $rows] = $multi->get();
```

For an existing `Analytics` instance, pass `toSearch()` to `addQuery()` and convert its response with `formatResponse()`:

```php
$multi->addQuery($analytics->toSearch(), 'metrics');
[$metrics, $rows] = $multi->get();
$dashboard = $analytics->formatResponse($metrics);
```

## Analytics tool for AI agents

An index that uses the [`AsTool`](laravel-ai.md) trait includes an `analytics` tool in `tools()`. The agent selects a `widget`, such as `trend`, and passes its arguments.

```php
class OrderIndex extends SigmieIndex
{
    use AsTool;
    // ...
}

// In your agent:
public function tools(): array
{
    return [
        ...app(OrderIndex::class)->tools("user_id:{$this->user->id}"),
    ];
}
```

Tool arguments that differ from the PHP API:

| Argument                                            | Meaning                                                                    |
| --------------------------------------------------- | -------------------------------------------------------------------------- |
| `date_field`                                        | The timeline. Must be a date field of the index.                           |
| `range`, `timezone_offset`                          | A period name such as `this_month` or `last_7_days`, and minutes east of UTC. |
| `field: null`                                       | A count with no field, for example on `grouped_trend`.                     |
| `sort`                                              | `"metric:asc"` or `"metric:desc"` on ranked widgets. `"field:direction"` on `table`. |
| `group_by_fields`                                   | Two or more compatible fields for `multi_breakdown` and `union_breakdown`. |
| `include_hits`, `hit_fields`, `hit_sort`, `hit_limit`, `hit_filters` | Return the matching documents with the widget.                |

Your code sets the `$baseFilter` argument of `tools()`, never the agent. Sigmie adds it with AND to every query, so the agent sees only that slice.
