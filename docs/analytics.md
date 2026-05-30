---
title: Analytics
short_description: Build dashboard analytics on a Sigmie index — KPIs, period-over-period deltas, time-series trends, breakdowns, distributions, percentiles, and cumulative growth — and expose them to AI agents.
keywords: [analytics, dashboard, trends, KPI, time series, breakdown, histogram, percentiles, AI agent]
category: Features
order: 3
related_pages: [aggregations, facets, laravel-ai]
---

# Analytics

`analytics()` is a dashboard vocabulary on top of [raw aggregations](aggregations.md). Instead of hand-building date histograms and pipeline aggregations, you compose **widgets** — the same shapes Stripe, Sentry and most app dashboards render — over a time window, and run them all in a single request.

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

`analytics()` is available on any [`SigmieIndex`](mappings.md). The first argument is the **timeline** — the date field every widget buckets and scopes by. The window defaults to the last 30 days.

## Metrics

Every measuring widget takes a `Metric`:

| Metric            | Meaning                          |
| ----------------- | -------------------------------- |
| `Metric::Sum`     | total of a numeric field         |
| `Metric::Avg`     | average of a numeric field       |
| `Metric::Min`     | minimum value                    |
| `Metric::Max`     | maximum value                    |
| `Metric::Count`   | number of events                 |
| `Metric::Unique`  | distinct values (cardinality)    |
| `Metric::Median`  | 50th percentile                  |

`Count` has no meaningful field, so it falls back to the timeline field when none is given.

## Widgets

`get()` returns a map of `widget name => normalised result`, ready to hand to a chart.

### KPI — a single number

```php
$orders->analytics('created_at')
    ->kpi('revenue', Metric::Sum, 'amount')
    ->kpi('customers', Metric::Unique, 'customer_id')
    ->get();

// ['revenue' => ['type' => 'kpi', 'value' => 48210.0, 'count' => 1240, ...], ...]
```

### KPI delta — value vs the previous period

Compares the window against the immediately preceding window of equal length.

```php
$orders->analytics('created_at')
    ->from($monthStart)->to($monthEnd)
    ->kpiDelta('revenue', Metric::Sum, 'amount')
    ->get();

// ['revenue' => ['value' => 300.0, 'previous' => 150.0, 'change_pct' => 100.0, ...]]
```

### Trend — a metric over time

The line/area/bar chart. Re-bucketing **per day → per month** is the same call with a different `CalendarInterval`:

```php
$orders->analytics('created_at')
    ->trend('revenue', Metric::Sum, 'amount', CalendarInterval::Day)
    ->get();

// ['revenue' => ['interval' => 'Day', 'series' => [
//     ['label' => '2024-01-01T00:00:00.000Z', 'value' => 150.0], ...
// ]]]
```

Intervals: `Minute`, `Hour`, `Day`, `Week`, `Month`, `Quarter`, `Year`.

### Grouped trend — one series per dimension

The stacked/grouped chart ("errors by level over time", "revenue by product over time").

```php
$orders->analytics('created_at')
    ->groupedTrend('by_product', Metric::Sum, 'amount', groupBy: 'product', interval: CalendarInterval::Day, limit: 5)
    ->get();

// ['by_product' => ['groups' => [
//     ['group' => 'A', 'series' => [['label' => ..., 'value' => 370.0], ...]], ...
// ]]]
```

### Breakdown — top-N ranked list

A dimension ranked by a metric ("top products by revenue", "top issues by event count").

```php
$orders->analytics('created_at')
    ->breakdown('top_products', 'product', Metric::Sum, 'amount', limit: 10)
    ->get();

// ['top_products' => ['rows' => [
//     ['key' => 'A', 'value' => 370.0, 'count' => 3], ...
// ]]]
```

### Distribution — histogram of a numeric field

```php
$orders->analytics('created_at')
    ->distribution('order_sizes', 'amount', interval: 100)
    ->get();

// ['order_sizes' => ['buckets' => [['label' => 0, 'count' => 3], ['label' => 100, 'count' => 1], ...]]]
```

### Percentiles — p50 / p75 / p95 / p99

The latency-style summary.

```php
$requests->analytics('created_at')
    ->percentiles('latency', 'duration_ms', [50, 95, 99])
    ->get();

// ['latency' => ['percentiles' => ['50' => 120.0, '95' => 880.0, '99' => 1450.0]]]
```

### Cumulative — running total

The growth curve ("total customers", "MRR to date"), built on a `cumulative_sum` pipeline.

```php
$orders->analytics('created_at')
    ->cumulative('growth', Metric::Sum, 'amount', CalendarInterval::Day)
    ->get();

// ['growth' => ['series' => [
//     ['label' => '2024-01-01...', 'value' => 150.0],
//     ['label' => '2024-01-02...', 'value' => 350.0], ...
// ]]]
```

## Filtering and the time window

`filters()` accepts the same [filter-parser](filter-parser.md) DSL as search, applied across every widget. `from()` / `to()` set the window; each widget scopes itself to it (a KPI delta also reaches back one window for its comparison).

## Analytics for AI agents

Add [`AsTool`](laravel-ai.md) to an index and its `tools()` suite includes an `analytics` tool. The agent picks a `widget` and arguments; "give me sales this month as a chart" becomes a `trend` call, and "show it per month instead" is the **same call with `interval: month`** — the agent adapts one argument and re-runs.

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
        // search, discover_filter_values, sample_documents, describe_index, analytics
        ...app(OrderIndex::class)->tools("user_id:{$this->user->id}"),
    ];
}
```

The agent supplies the `date_field` (validated against the index's date fields) and the `metric`/`field` (from its numeric fields). As with the other tools, the optional `$baseFilter` is server-controlled scoping — AND-ed into every query and never taken from the agent — so an agent can only ever see its own slice of the data.
