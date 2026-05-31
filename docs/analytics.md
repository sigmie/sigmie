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

`filters()` accepts the same [filter-parser](filter-parser.md) DSL as search, applied across every widget. `filterQuery()` ANDs a hard query clause (a query object) into the same filter context — exactly like [`NewSearch::filterQuery()`](search.md):

```php
use Sigmie\Query\Queries\Term\Range;

$orders->analytics('created_at')
    ->filters("status:'paid'")
    ->filterQuery(new Range('amount', ['>=' => 100]))   // composed with the DSL above
    ->kpi('big_orders', Metric::Sum, 'amount')
    ->get();
```

`from()` / `to()` set the window; each widget scopes itself to it (a KPI delta also reaches back one window for its comparison).

## Timezone

Analytics is UTC by default — which silently mis-buckets daily/weekly/monthly charts for users elsewhere (a Tokyo sale just after local midnight lands in the previous UTC day). Set the timezone and Elasticsearch aligns bucket boundaries to local time and handles DST:

```php
$orders->analytics('created_at')
    ->timezoneOffset(540)             // minutes east of UTC: Tokyo = 540, New York = -300, India = 330
    ->range(Period::ThisMonth)
    ->trend('sales', Metric::Sum, 'amount', CalendarInterval::Day)
    ->get();
```

From a browser, pass `-new Date().getTimezoneOffset()` (its sign is inverted). Minutes — not hours — so half-hour zones like India (`330`) and Nepal (`345`) work. For DST-correctness across a transition, pass an IANA name instead: `->timezone('Asia/Tokyo')`.

The window edges follow too: build `from()`/`to()` as `DateTimeImmutable` in the user's zone (their offset rides along in the ISO string), or use a named range, which is resolved in the configured timezone.

## Named ranges

`range()` sets the window to a relative period, resolved in the configured timezone to absolute instants (so the query stays cacheable). Weeks start Monday (ISO).

```php
use Sigmie\Analytics\Enums\Period;

$orders->analytics('created_at')->timezoneOffset(540)->range(Period::Last7Days);
```

`Today, Yesterday, ThisWeek, LastWeek, ThisMonth, LastMonth, ThisQuarter, LastQuarter, ThisYear, LastYear, Last7Days, Last30Days, Last90Days`.

A **calendar** range also makes `kpiDelta` compare against the *previous instance* of that period rather than an equal-length window — `ThisMonth` → vs last calendar month, `ThisWeek` → vs last week. Raw `from()`/`to()` keep the equal-duration comparison.

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

The agent supplies the `date_field` (validated against the index's date fields), the `metric`/`field` (from its numeric fields), and optionally a `range` (`this_month`, `last_7_days`, …) and `timezone_offset` (minutes east of UTC) so charts land in the user's local time. As with the other tools, the optional `$baseFilter` is server-controlled scoping — AND-ed into every query and never taken from the agent — so an agent can only ever see its own slice of the data.
