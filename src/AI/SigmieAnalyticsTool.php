<?php

declare(strict_types=1);

namespace Sigmie\AI;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sigmie\Analytics\Analytics;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Analytics\Enums\Period;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\SigmieIndex;

/**
 * Exposes a Sigmie index as a dashboard-analytics tool for an agent.
 *
 * One tool, many widget shapes (selected by `widget`): a KPI number, a KPI with a
 * period-over-period delta, a time-series trend, a stacked/grouped trend, a top-N breakdown,
 * a value distribution, a cumulative growth curve, percentiles, a five-number stats summary,
 * a table of the actual documents, an ordered funnel, a two-dimension heatmap, a cohort
 * retention grid, and a geo (geohash) map. The agent adapts a chart by re-calling with
 * different arguments — "per day → per month" is the same call with a different `interval`.
 *
 * Requires `laravel/ai` to be installed.
 *
 * Usage:
 *   new SigmieAnalyticsTool(app(OrderIndex::class))
 *   new SigmieAnalyticsTool(app(OrderIndex::class), baseFilters: "user_id:{$user->id}")
 *
 * The `$baseFilters` is server-controlled scoping: it is AND-ed into every query and is NEVER
 * taken from the agent, so the agent cannot read outside the scope.
 */
class SigmieAnalyticsTool implements Tool
{
    use DescribesIndexFields;
    use HandlesToolErrors;

    public function __construct(
        protected SigmieIndex $index,
        protected string $baseFilters = '',
    ) {}

    public function name(): string
    {
        return 'analytics';
    }

    public function description(): string
    {
        $dateFields = $this->fieldNamesOfTypes(['date']);
        $numericFields = $this->fieldNamesOfTypes(['number']);
        $keywordFields = $this->fieldNamesOfTypes(['keyword', 'text']);
        $geoFields = $this->fieldNamesOfTypes(['geo']);

        $description = sprintf("Dashboard analytics for the '%s' index — compute a single chart/widget per call.", $this->index->name());

        $description .= "\n\nWidgets (`widget`):\n"
            ."- kpi: one number for the period (needs metric, field)\n"
            ."- kpi_delta: kpi plus % change vs the previous equal-length period (needs metric, field)\n"
            ."- trend: a metric over time — line/area/bar (needs metric, field, interval)\n"
            ."- cumulative: running total over time — growth curve (needs metric, field, interval)\n"
            ."- grouped_trend: one trend line per value of group_by — stacked chart (needs metric, field, group_by, interval)\n"
            ."- breakdown: top-N group_by values ranked by a metric (needs group_by, metric, field)\n"
            ."- distribution: histogram of a numeric field (needs field, bucket_size)\n"
            ."- percentiles: p50/p75/p95/p99 of a numeric field (needs field)\n"
            ."- stats: count/min/max/avg/sum of a numeric field in one tile (needs field)\n"
            ."- table: the actual matching documents — the rows behind a number, not a metric (needs fields, sort, limit)\n"
            ."- funnel: ordered step conversion with drop-off % (needs steps)\n"
            ."- heatmap: a row_field × col_field matrix, a metric per cell (needs row_field, col_field; metric+field optional, defaults to count)\n"
            ."- retention: cohort grid — entities by cohort period, counted distinct per later period (needs cohort_field, id_field, interval)\n"
            .'- geo: a count bucketed into geohash cells of a geo_point field — a map heatmap (needs field, precision)';

        // Phrasing → widget hints. Disambiguates the common trap where 'monthly/weekly/daily X
        // over <period>' reads as 'one number for the period' (kpi) instead of 'a series bucketed
        // at that interval' (trend). Without this, small models (gpt-4o-mini, gpt-4.1-mini) drop
        // the time-series when the user names the bucket inside the phrase.
        $description .= "\n\nChoosing a widget — match the phrasing:\n"
            ."- \"daily / weekly / monthly / hourly X over <period>\" or \"X per day/week/month\" → trend (use interval=day|week|month|hour). NOT kpi — the bucket word means a series.\n"
            ."- \"X over time\", \"trend of X\", \"X by <date_field>\" → trend\n"
            ."- \"running total\", \"cumulative X\", \"growth curve\" → cumulative\n"
            ."- \"top N <thing> by X\", \"best <thing>\", \"which <thing> drove the most X\" → breakdown (group_by=<thing>)\n"
            ."- \"X this month\" / \"average X last week\" with no bucket word → kpi (or kpi_delta when the user compares to a prior period)\n"
            ."- \"distribution of X\", \"histogram of X\" → distribution\n"
            ."- \"p50/p75/p95/p99\", \"percentiles of X\", \"median X\" → percentiles\n"
            ."- \"summary of X\", \"min/max/avg of X\", \"stats for X\" → stats\n"
            ."- \"show/list the actual <docs>\", \"recent <docs>\", \"the rows behind X\", \"examples of X\" → table\n"
            ."- \"funnel\", \"conversion from A → B → C\", \"drop-off between steps\" → funnel (steps in order)\n"
            ."- \"A by B\", \"<dim1> vs <dim2>\", \"a matrix/heatmap of A and B\" → heatmap (row_field=A, col_field=B)\n"
            ."- \"retention\", \"cohort analysis\", \"how many users come back\" → retention\n"
            .'- "on a map", "by location / area / region (coordinates)", "geo heatmap" → geo';

        $description .= "\n\nMetrics (`metric`): sum, avg, min, max, count, unique (distinct), median.";
        $description .= "\n\nIntervals (`interval`): a calendar unit (minute, hour, day, week, month, quarter, year) or a fixed interval — a multiple of a unit like 15d, 12h, 90m, 30s.";
        $description .= "\n\nRelative window (`range`, preferred over from/to): today, yesterday, this_week, last_week, this_month, last_month, this_quarter, last_quarter, this_year, last_year, last_7_days, last_30_days, last_90_days. A calendar range makes kpi_delta compare against the previous instance (this_month vs last_month).";
        $description .= "\n\nTimezone (`timezone_offset`): minutes east of UTC (Tokyo 540, New York -300). Aligns buckets and ranges to the user's local time. From a browser, send the negation of Date.getTimezoneOffset().";

        $description .= "\n\nTimeline fields for `date_field`: ".(
            $dateFields !== [] ? implode(', ', $dateFields) : '(none — this index has no date field)'
        );
        $description .= "\nNumeric fields for `field`: ".(
            $numericFields !== [] ? implode(', ', $numericFields) : '(none)'
        );
        $description .= "\nKeyword fields for `group_by` / `row_field` / `col_field` / `id_field`: ".(
            $keywordFields !== [] ? implode(', ', $keywordFields) : '(none)'
        );
        $description .= "\nGeo fields for `field` (geo widget): ".(
            $geoFields !== [] ? implode(', ', $geoFields) : '(none — this index has no geo_point field)'
        );

        $description .= "\n\nTime window: `from` and `to` are ISO dates (default: last 30 days).\n"
            ."Narrow with `filters` (same DSL as the search tool, e.g. \"status:'paid' AND amount>10\") — it scopes this widget to that slice of the window. Call describe_index for the full field list and filter syntax.\n"
            .'Comparing slices: for an ordered funnel (total → engaged → completed) use the funnel widget with `steps`; for an ad-hoc A vs B, call this tool once per slice over the SAME window — each call narrows with its own `filters` — and read the numbers side by side.';

        return $description."\n\nExamples (`widget` → arguments):\n".$this->examples();
    }

    /**
     * One copy-pasteable argument example per widget, grounded in this index's own fields (the first
     * date / numeric / keyword field), so the model sees the exact JSON shape each widget expects —
     * including a sliced KPI showing how `filters` narrows a single widget.
     */
    protected function examples(): string
    {
        $date = $this->fieldNamesOfTypes(['date'])[0] ?? '<date_field>';
        $number = $this->fieldNamesOfTypes(['number'])[0] ?? '<numeric_field>';
        // group_by is typically a keyword or a category (a text field with a keyword sub-field).
        $keyword = $this->fieldNamesOfTypes(['keyword', 'text'])[0] ?? '<keyword_field>';
        $keyword2 = $this->fieldNamesOfTypes(['keyword', 'text'])[1] ?? $keyword;
        $geo = $this->fieldNamesOfTypes(['geo'])[0] ?? '<geo_point_field>';

        $examples = [
            'total for the month' => ['widget' => 'kpi', 'date_field' => $date, 'metric' => 'sum', 'field' => $number, 'range' => 'this_month'],
            'this month vs last month' => ['widget' => 'kpi_delta', 'date_field' => $date, 'metric' => 'sum', 'field' => $number, 'range' => 'this_month'],
            'daily count over 30 days' => ['widget' => 'trend', 'date_field' => $date, 'metric' => 'count', 'interval' => 'day', 'range' => 'last_30_days'],
            'running total this quarter' => ['widget' => 'cumulative', 'date_field' => $date, 'metric' => 'sum', 'field' => $number, 'interval' => 'day', 'range' => 'this_quarter'],
            'daily series per group' => ['widget' => 'grouped_trend', 'date_field' => $date, 'metric' => 'sum', 'field' => $number, 'group_by' => $keyword, 'interval' => 'day', 'range' => 'last_30_days'],
            'top 5 groups by total' => ['widget' => 'breakdown', 'date_field' => $date, 'group_by' => $keyword, 'metric' => 'sum', 'field' => $number, 'limit' => 5, 'range' => 'this_month'],
            'histogram of a number' => ['widget' => 'distribution', 'date_field' => $date, 'field' => $number, 'bucket_size' => 100, 'range' => 'this_month'],
            'p50/p95/p99 of a number' => ['widget' => 'percentiles', 'date_field' => $date, 'field' => $number, 'percents' => '50,95,99', 'range' => 'this_month'],
            'summary of a number' => ['widget' => 'stats', 'date_field' => $date, 'field' => $number, 'range' => 'this_month'],
            'the 20 biggest rows' => ['widget' => 'table', 'date_field' => $date, 'fields' => sprintf('%s,%s', $keyword, $number), 'sort' => $number.':desc', 'limit' => 20, 'range' => 'this_month'],
            'conversion funnel' => ['widget' => 'funnel', 'date_field' => $date, 'steps' => sprintf('[{"label":"all","filter":"%s:\'a\'"},{"label":"converted","filter":"%s:\'b\'"}]', $keyword, $keyword), 'range' => 'this_month'],
            'matrix of two dimensions' => ['widget' => 'heatmap', 'date_field' => $date, 'row_field' => $keyword, 'col_field' => $keyword2, 'metric' => 'sum', 'field' => $number, 'range' => 'this_month'],
            'weekly cohort retention' => ['widget' => 'retention', 'date_field' => $date, 'cohort_field' => $date, 'id_field' => $keyword, 'interval' => 'week', 'range' => 'last_90_days'],
            'counts on a map' => ['widget' => 'geo', 'date_field' => $date, 'field' => $geo, 'precision' => 5, 'range' => 'this_month'],
            'one slice of the window' => ['widget' => 'kpi', 'date_field' => $date, 'metric' => 'sum', 'field' => $number, 'filters' => sprintf("%s:'…'", $keyword), 'range' => 'this_month'],
        ];

        $lines = array_map(
            static fn (string $label, array $args): string => sprintf('- %s: %s', $label, json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            array_keys($examples),
            array_values($examples),
        );

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        // `widget` and `date_field` are plain required; every optional param is `nullable()->required()`
        // so the schema stays valid under OpenAI's strict function-calling (callers pass null to omit).
        return [
            'widget' => $schema->string()->description('kpi | kpi_delta | trend | cumulative | grouped_trend | breakdown | distribution | percentiles | stats | table | funnel | heatmap | retention | geo')->required(),
            'date_field' => $schema->string()->description('Timeline (date) field to bucket/scope by')->required(),
            'metric' => $schema->string()->description('sum | avg | min | max | count | unique | median (pass null for distribution, percentiles, stats, table, funnel, retention, geo; optional for heatmap — defaults to count)')->nullable()->required(),
            'field' => $schema->string()->description('Numeric field the metric is computed on; also the numeric field for stats and the geo_point field for the geo widget (pass null for count)')->nullable()->required(),
            'interval' => $schema->string()->description('Time bucket for trends and retention: a calendar unit (minute | hour | day | week | month | quarter | year) or a fixed interval like 15d | 12h | 90m (default day)')->default('day')->nullable()->required(),
            'group_by' => $schema->string()->description('Keyword field to group/break down by, for breakdown and grouped_trend (pass null otherwise)')->nullable()->required(),
            'limit' => $schema->integer()->description('Max groups for breakdown/grouped_trend, rows for table, or cells per axis for heatmap (default 10)')->default(10)->nullable()->required(),
            'bucket_size' => $schema->integer()->description('Bucket width for the distribution widget (pass null otherwise)')->nullable()->required(),
            'percents' => $schema->string()->description('Comma-separated percentiles for the percentiles widget, e.g. "50,75,95,99" (pass null for the default)')->nullable()->required(),
            'fields' => $schema->string()->description('Comma-separated source fields to return for the table widget, e.g. "id,amount" (pass null for the full document)')->nullable()->required(),
            'sort' => $schema->string()->description('Sort for the table widget as "field:dir", e.g. "amount:desc" (pass null for default descending)')->nullable()->required(),
            'steps' => $schema->string()->description('Funnel steps as an ORDERED JSON array of {label, filter} objects, e.g. [{"label":"visited","filter":"event:\'visit\'"},{"label":"paid","filter":"event:\'paid\'"}] (pass null otherwise)')->nullable()->required(),
            'row_field' => $schema->string()->description('Heatmap row dimension — a keyword field (pass null otherwise)')->nullable()->required(),
            'col_field' => $schema->string()->description('Heatmap column dimension — a keyword field (pass null otherwise)')->nullable()->required(),
            'cohort_field' => $schema->string()->description('Retention cohort date field — entities are grouped by the period of this date (pass null otherwise)')->nullable()->required(),
            'id_field' => $schema->string()->description('Retention entity id — a keyword field counted distinct per period (pass null otherwise)')->nullable()->required(),
            'precision' => $schema->integer()->description('Geohash precision for the geo widget, 1 (coarse) to 12 (fine), default 5 (pass null otherwise)')->default(5)->nullable()->required(),
            'range' => $schema->string()->description('Named relative window, e.g. this_month | last_7_days (pass null to use from/to)')->nullable()->required(),
            'timezone_offset' => $schema->integer()->description('Timezone as minutes east of UTC, e.g. 540 for Tokyo (pass null for UTC)')->nullable()->required(),
            'from' => $schema->string()->description('ISO start date, inclusive — ignored when range is set (pass null for 30 days ago)')->nullable()->required(),
            'to' => $schema->string()->description('ISO end date, exclusive — ignored when range is set (pass null for now)')->nullable()->required(),
            'filters' => $schema->string()->description('Filter expression, same DSL as the search tool — scopes this widget to a slice of the window (pass null for none)')->nullable()->required(),
        ];
    }

    public function handle(Request $request): string
    {
        return $this->guard(fn (): string => json_encode($this->result($request), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * The widget result as an array, for callers that want data instead of the JSON string handle() returns.
     */
    public function result(Request $request): array
    {
        $widget = trim((string) ($request['widget'] ?? ''));
        $dateField = $this->dateField((string) ($request['date_field'] ?? ''));

        $analytics = $this->index->analytics(
            $dateField,
            $this->date($request['from'] ?? null),
            $this->date($request['to'] ?? null),
        );

        if (($offset = $request['timezone_offset'] ?? null) !== null && $offset !== '') {
            $analytics->timezoneOffset((int) $offset);
        }

        if (($range = trim((string) ($request['range'] ?? ''))) !== '') {
            $analytics->range(
                Period::tryFrom($range) ?? throw new InvalidArgumentException(sprintf(
                    "Unknown range '%s'. Use one of: today, yesterday, this_week, last_week, this_month, last_month, this_quarter, last_quarter, this_year, last_year, last_7_days, last_30_days, last_90_days.",
                    $range
                ))
            );
        }

        if (($filters = $this->filters($request)) !== '') {
            $analytics->filters($filters);
        }

        $this->build($analytics, $widget, $request);

        return $analytics->get()['result'] ?? [];
    }

    protected function build(Analytics $analytics, string $widget, Request $request): void
    {
        $metric = fn (): Metric => $this->metric((string) ($request['metric'] ?? ''));
        $field = (string) ($request['field'] ?? '');
        $interval = fn (): CalendarInterval|string => $this->interval((string) ($request['interval'] ?? 'day'));
        $groupBy = fn (): string => $this->required($request, 'group_by');
        $limit = max(1, (int) ($request['limit'] ?? 10));

        match ($widget) {
            'kpi' => $analytics->kpi('result', $metric(), $field),
            'kpi_delta' => $analytics->kpiDelta('result', $metric(), $field),
            'trend' => $analytics->trend('result', $metric(), $field, $interval()),
            'cumulative' => $analytics->cumulative('result', $metric(), $field, $interval()),
            'grouped_trend' => $analytics->groupedTrend('result', $metric(), $this->required($request, 'field'), $groupBy(), $interval(), $limit),
            'breakdown' => $analytics->breakdown('result', $groupBy(), $metric(), $field, $limit),
            'distribution' => $analytics->distribution('result', $this->required($request, 'field'), (int) ($request['bucket_size'] ?? throw new InvalidArgumentException('The distribution widget requires bucket_size.'))),
            'percentiles' => $analytics->percentiles('result', $this->required($request, 'field'), $this->percents($request)),
            'stats' => $analytics->stats('result', $this->required($request, 'field')),
            'table' => $analytics->table('result', $this->csvList($request, 'fields'), $limit, $this->optional($request, 'sort')),
            'funnel' => $analytics->funnel('result', $this->steps($request)),
            'heatmap' => $analytics->heatmap('result', $this->required($request, 'row_field'), $this->required($request, 'col_field'), $this->metricOrCount($request), $field, $limit, $limit),
            'retention' => $analytics->retention('result', $this->required($request, 'cohort_field'), $this->required($request, 'id_field'), $interval()),
            'geo' => $analytics->geo('result', $this->required($request, 'field'), max(1, (int) ($request['precision'] ?? 5))),
            default => throw new InvalidArgumentException(sprintf("Unknown widget '%s'. Use one of: kpi, kpi_delta, trend, cumulative, grouped_trend, breakdown, distribution, percentiles, stats, table, funnel, heatmap, retention, geo.", $widget)),
        };
    }

    protected function dateField(string $field): string
    {
        $field = trim($field);

        $dateFields = $this->fieldNamesOfTypes(['date']);

        if ($field === '' || ! in_array($field, $dateFields, true)) {
            throw new InvalidArgumentException(sprintf(
                "Unknown date_field '%s'. Available timeline fields: %s.",
                $field,
                $dateFields !== [] ? implode(', ', $dateFields) : '(none)'
            ));
        }

        return $field;
    }

    protected function metric(string $metric): Metric
    {
        return Metric::tryFrom(trim($metric)) ?? throw new InvalidArgumentException(sprintf(
            "Unknown metric '%s'. Use one of: sum, avg, min, max, count, unique, median.",
            $metric
        ));
    }

    protected function interval(string $interval): CalendarInterval|string
    {
        $interval = strtolower(trim($interval));

        // Fixed interval: a multiple of a unit (15d, 90m, 12h, 30s, 250ms) — Elasticsearch needs
        // fixed_interval for these, since calendar_interval only allows a single unit.
        if (preg_match('/^\d+(ms|s|m|h|d)$/', $interval) === 1) {
            return $interval;
        }

        return match ($interval) {
            'minute' => CalendarInterval::Minute,
            'hour' => CalendarInterval::Hour,
            '', 'day' => CalendarInterval::Day,
            'week' => CalendarInterval::Week,
            'month' => CalendarInterval::Month,
            'quarter' => CalendarInterval::Quarter,
            'year' => CalendarInterval::Year,
            default => throw new InvalidArgumentException(sprintf(
                "Unknown interval '%s'. Use a calendar unit (minute, hour, day, week, month, quarter, year) or a fixed interval like 15d, 12h, 90m.",
                $interval
            )),
        };
    }

    /**
     * @return list<int|float>
     */
    protected function percents(Request $request): array
    {
        $raw = trim((string) ($request['percents'] ?? ''));

        if ($raw === '') {
            return [50, 75, 95, 99];
        }

        return array_values(array_filter(array_map(
            static fn (string $p): float => (float) trim($p),
            explode(',', $raw)
        ), static fn (float $p): bool => $p > 0 && $p < 100));
    }

    /**
     * The metric for widgets where it is optional (heatmap), defaulting to Count when omitted.
     */
    protected function metricOrCount(Request $request): Metric
    {
        $metric = trim((string) ($request['metric'] ?? ''));

        return $metric === '' ? Metric::Count : $this->metric($metric);
    }

    /**
     * Ordered funnel steps from a JSON array of {label, filter} objects into a label => filter map.
     *
     * @return array<string, string>
     */
    protected function steps(Request $request): array
    {
        $raw = trim((string) ($request['steps'] ?? ''));

        if ($raw === '') {
            throw new InvalidArgumentException('The funnel widget requires steps — a JSON array of {label, filter} objects, in order.');
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('funnel steps must be a JSON array of {label, filter} objects, e.g. [{"label":"visited","filter":"event:\'visit\'"}].');
        }

        $steps = [];

        foreach ($decoded as $step) {
            $label = trim((string) ($step['label'] ?? ''));
            $filter = trim((string) ($step['filter'] ?? ''));

            if (! ($label !== '' && $filter !== '')) {
                throw new InvalidArgumentException('Each funnel step needs a non-empty label and filter.');
            }

            $steps[$label] = $filter;
        }

        return $steps !== [] ? $steps : throw new InvalidArgumentException('The funnel widget requires at least one step.');
    }

    /**
     * A comma-separated argument parsed into a trimmed list (empty when absent).
     *
     * @return list<string>
     */
    protected function csvList(Request $request, string $key): array
    {
        $raw = trim((string) ($request[$key] ?? ''));

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $v): string => trim($v), explode(',', $raw)),
            static fn (string $v): bool => $v !== '',
        ));
    }

    /**
     * An optional string argument: the trimmed value, or null when absent.
     */
    protected function optional(Request $request, string $key): ?string
    {
        $value = trim((string) ($request[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    protected function date(mixed $value): ?DateTimeInterface
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : new DateTimeImmutable($value);
    }

    protected function filters(Request $request): string
    {
        $parts = array_values(array_filter([
            $this->baseFilters,
            trim((string) ($request['filters'] ?? '')),
        ], static fn (string $f): bool => $f !== ''));

        return implode(' AND ', array_map(static fn (string $f): string => sprintf('(%s)', $f), $parts));
    }

    protected function required(Request $request, string $key): string
    {
        $value = trim((string) ($request[$key] ?? ''));

        return $value !== '' ? $value : throw new InvalidArgumentException(sprintf("The '%s' parameter is required for this widget.", $key));
    }
}
