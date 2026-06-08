---
title: Analytics
short_description: Build dashboard analytics on a Sigmie index — KPIs, period-over-period deltas, time-series trends, breakdowns, distributions, percentiles, cumulative growth, funnels, cohort retention, heatmaps, geo maps and document tables — and expose them to AI agents.
keywords: [analytics, dashboard, trends, KPI, time series, breakdown, histogram, percentiles, funnel, retention, cohort, heatmap, geo, table, multi-search, AI agent]
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

### Stats — the five-number summary

`count`, `min`, `max`, `avg` and `sum` of a numeric field in one tile, built on the `stats` aggregation.

```php
$orders->analytics('created_at')
    ->stats('order_size', 'amount')
    ->get();

// ['order_size' => ['type' => 'stats', 'count' => 5, 'min' => 30.0, 'max' => 200.0, 'avg' => 90.0, 'sum' => 450.0]]
```

### Table — the documents behind a number

Every widget so far returns an aggregated number. `table` returns the **actual matching documents** — the rows behind a slice ("the 20 most recent orders", "the latest errors") — via a `top_hits` aggregation, so it scopes to the same window and per-widget `filter:` as the rest. `fields` limits the returned source (omit for the full document); `sort` is a `"field:dir"` string (defaults to descending).

```php
$orders->analytics('created_at')
    ->table('recent', fields: ['amount', 'product'], limit: 20, sort: 'amount:desc')
    ->get();

// ['recent' => ['type' => 'table', 'rows' => [
//     ['id' => 'ord_8f2c…', 'document' => ['amount' => 200, 'product' => 'A']], ...
// ]]]
```

`top_hits` returns the top N of one bucket — it is **not** a paginated, field-collapsed search. For an interactive table with deep pagination use a regular [`search()`](search.md); to batch that search into the same request as the dashboard, see [multi-search](#one-request-with-multi-search) below.

### Funnel — ordered step conversion

How many documents reach each stage and the conversion between them ("visited → signed up → paid"). `steps` is an **ordered** map of `label => slice`, where each slice is a [filter-parser](filter-parser.md) string or a query object. Each step reports its `count`, its `conversion` against the first step, and its `step_conversion` against the previous step.

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

### Heatmap — one dimension by another

A two-dimensional matrix — `rowField` × `colField`, each cell carrying a metric (defaults to a count). Built on nested `terms` buckets.

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

### Retention — a cohort grid

Entities (identified by `idField`) grouped by the period of their `cohortField` date, then counted **distinct** across each later period of the timeline — the classic triangular cohort table. Built on a `date_histogram` of the cohort field, a nested `date_histogram` of the timeline, and a `cardinality` of the id.

```php
$signups->analytics('active_at')
    ->retention('cohorts', cohortField: 'signup_at', idField: 'user_id', interval: CalendarInterval::Week)
    ->get();

// ['cohorts' => ['type' => 'retention', 'cohorts' => [
//     ['cohort' => '2024-01-01...', 'size' => 2, 'periods' => [
//         ['label' => '2024-01-01...', 'value' => 2],
//         ['label' => '2024-01-02...', 'value' => 1],
//     ]], ...
// ]]]
```

### Geo — a map heatmap

A metric bucketed into geohash cells of a `geo_point` field, for a map overlay. Higher `precision` means smaller, more granular cells. Built on the `geohash_grid` aggregation.

```php
$orders->analytics('created_at')
    ->geo('areas', 'location', precision: 5)
    ->get();

// ['areas' => ['type' => 'geo', 'precision' => 5, 'buckets' => [
//     ['geohash' => 'u173z', 'value' => 2, 'count' => 2], ...
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

### Per-widget filter (funnels)

`filters()` / `filterQuery()` apply to *every* widget. To count a *different* slice per widget — a funnel of `total → engaged → completed` over the same window — pass a `filter:` to the widget itself. It is ANDed into that widget's own scope only, so the whole funnel is a single request. Like `filters()`, it accepts either the filter DSL string or a query object:

```php
use Sigmie\Query\Queries\Term\Term;

$orders->analytics('created_at')
    ->from($start)->to($end)
    ->kpi('total', Metric::Unique, 'customer_id')
    ->kpi('engaged', Metric::Unique, 'customer_id', filter: "status:'opened' OR status:'completed'")
    ->kpi('completed', Metric::Unique, 'customer_id', filter: new Term('status', 'completed'))
    ->get();
```

A string is parsed with the same [filter-parser](filter-parser.md) DSL (typed against the index mapping); a query object is taken as-is. Omit it and the widget is unchanged.

Every widget takes the same `filter:` argument (as its last parameter) — so you can scope a trend, a breakdown, a `kpiDelta`, and the rest to a slice too:

```php
$orders->analytics('created_at')
    ->from($start)->to($end)
    ->trend('paid_revenue', Metric::Sum, 'amount', CalendarInterval::Day, filter: "status:'paid'")
    ->breakdown('top_vip_products', 'product', Metric::Sum, 'amount', filter: "tier:'vip'")
    ->kpiDelta('refunds', Metric::Count, filter: "status:'refunded'")   // applies to both the current and comparison window
    ->get();
```

### Per-widget window

`from()` / `to()` set one window for the whole dashboard. To give a single widget its **own** window — an all-time headline next to a last-30-days trend — pass a `window:` to the widget. Like the per-widget `filter:`, it scopes that one widget only, so the mixed-window dashboard stays a single request. It accepts a named [`Period`](#named-ranges) or a `[$from, $to]` pair:

```php
use Sigmie\Analytics\Enums\Period;

$orders->analytics('created_at')
    ->from($last30Start)->to($now)
    ->kpi('lifetime_revenue', Metric::Sum, 'amount', window: [$accountStart, $now])  // all-time
    ->kpi('this_year', Metric::Sum, 'amount', window: Period::ThisYear)               // named period
    ->trend('recent_revenue', Metric::Sum, 'amount', CalendarInterval::Day)           // dashboard window
    ->get();
```

`window:` is the last argument of every widget method, alongside `filter:`. A `kpiDelta` with a custom `window:` compares against the immediately preceding equal-length window (the named-period comparison only applies to the dashboard-wide `range()`).

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

## Widgets and hits in one request

`get()` returns only the widget map. When a dashboard needs the numbers **and** the matching documents behind those numbers, use `search()` to customize the underlying Elasticsearch search and `getWithHits()` to return both.

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

The important detail is `post_filter`: it narrows the returned hits without changing the aggregations. That is useful when the headline card should count the full scoped population, but the table should show one row slice.

## One request with multi-search

`get()` already runs every widget in a single request. `getWithHits()` covers the common case where the same search can return dashboard widgets and rows. When the rows need a completely different query shape, batch both into one `_msearch`.

`newAnalytics()` starts a dashboard **inside** the multi-search — compose widgets on it as usual, and its response slot comes back already mapped to the widget result map:

```php
$multi = $sigmie->newMultiSearch();

$multi->newAnalytics($orders, 'created_at', 'metrics')        // the whole dashboard
    ->from($start)->to($end)
    ->kpiDelta('revenue', Metric::Sum, 'amount')
    ->trend('revenue_over_time', Metric::Sum, 'amount', CalendarInterval::Day);

$multi->newSearch($orders->name(), 'rows')                    // a paginated rows table
    ->queryString('')->filters("status:'paid'")->page(3, 20);

[$metrics, $rows] = $multi->get();                            // one HTTP round-trip
// $metrics is already the widget name => result map
```

If you built the `Analytics` elsewhere, add it to the batch with `toSearch()` instead, then map its slot by hand with `formatResponse()`:

```php
$multi->addQuery($analytics->toSearch(), 'metrics');
[$metrics, $rows] = $multi->get();
$dashboard = $analytics->formatResponse($metrics);
```

`formatResponse()` maps the raw response's aggregations through every widget, exactly as `get()` does internally. Use `get()` for a standalone dashboard, `getWithHits()` when one search can also return rows, and `newAnalytics()` (or `toSearch()` + `formatResponse()`) when analytics shares a round-trip with a separate query.

## Analytics for AI agents

Add [`AsTool`](laravel-ai.md) to an index and its `tools()` suite includes an `analytics` tool. The agent picks a `widget` — `kpi`, `kpi_delta`, `trend`, `cumulative`, `grouped_trend`, `breakdown`, `distribution`, `percentiles`, `stats`, `table`, `funnel`, `heatmap`, `retention` or `geo` — and arguments; "give me sales this month as a chart" becomes a `trend` call, and "show it per month instead" is the **same call with `interval: month`** — the agent adapts one argument and re-runs. The tool's description carries a grounded example per widget (using the index's own fields), so the model sees the exact argument shape each one expects.

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

The agent supplies the `date_field` (validated against the index's date fields), the `metric`/`field` (from its numeric fields), and optionally a `range` (`this_month`, `last_7_days`, …) and `timezone_offset` (minutes east of UTC) so charts land in the user's local time. When the user asks for a metric/chart and examples or rows behind it, the agent can set `include_hits=1`, plus optional `hit_fields`, `hit_sort`, `hit_limit`, and `hit_filters`. As with the other tools, the optional `$baseFilter` is server-controlled scoping — AND-ed into every query and never taken from the agent — so an agent can only ever see its own slice of the data.
