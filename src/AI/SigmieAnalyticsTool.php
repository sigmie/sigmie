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
 * a value distribution, a cumulative growth curve, and percentiles. The agent adapts a chart
 * by re-calling with different arguments — "per day → per month" is the same call with a
 * different `interval`.
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

        $description = sprintf("Dashboard analytics for the '%s' index — compute a single chart/widget per call.", $this->index->name());

        $description .= "\n\nWidgets (`widget`):\n"
            ."- kpi: one number for the period (needs metric, field)\n"
            ."- kpi_delta: kpi plus % change vs the previous equal-length period (needs metric, field)\n"
            ."- trend: a metric over time — line/area/bar (needs metric, field, interval)\n"
            ."- cumulative: running total over time — growth curve (needs metric, field, interval)\n"
            ."- grouped_trend: one trend line per value of group_by — stacked chart (needs metric, field, group_by, interval)\n"
            ."- breakdown: top-N group_by values ranked by a metric (needs group_by, metric, field)\n"
            ."- distribution: histogram of a numeric field (needs field, bucket_size)\n"
            .'- percentiles: p50/p75/p95/p99 of a numeric field (needs field)';

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
            .'- "p50/p75/p95/p99", "percentiles of X", "median X" → percentiles';

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

        return $description."\n\n"
            ."Time window: `from` and `to` are ISO dates (default: last 30 days).\n"
            ."Narrow with `filters` (same DSL as the search tool, e.g. \"status:'paid' AND amount>10\") — it scopes this widget to that slice of the window. Call describe_index for the full field list and filter syntax.\n"
            .'Comparing slices (a funnel like total → engaged → completed, or A vs B): call this tool once per slice over the SAME window — each call narrows with its own `filters` — and read the numbers side by side.';
    }

    public function schema(JsonSchema $schema): array
    {
        // `widget` and `date_field` are plain required; every optional param is `nullable()->required()`
        // so the schema stays valid under OpenAI's strict function-calling (callers pass null to omit).
        return [
            'widget' => $schema->string()->description('kpi | kpi_delta | trend | cumulative | grouped_trend | breakdown | distribution | percentiles')->required(),
            'date_field' => $schema->string()->description('Timeline (date) field to bucket/scope by')->required(),
            'metric' => $schema->string()->description('sum | avg | min | max | count | unique | median (pass null for distribution/percentiles)')->nullable()->required(),
            'field' => $schema->string()->description('Numeric field the metric is computed on (pass null for count)')->nullable()->required(),
            'interval' => $schema->string()->description('Time bucket for trends: a calendar unit (minute | hour | day | week | month | quarter | year) or a fixed interval like 15d | 12h | 90m (default day)')->default('day')->nullable()->required(),
            'group_by' => $schema->string()->description('Keyword field to group/break down by, for breakdown and grouped_trend (pass null otherwise)')->nullable()->required(),
            'limit' => $schema->integer()->description('Max groups for breakdown/grouped_trend (default 10)')->default(10)->nullable()->required(),
            'bucket_size' => $schema->integer()->description('Bucket width for the distribution widget (pass null otherwise)')->nullable()->required(),
            'percents' => $schema->string()->description('Comma-separated percentiles for the percentiles widget, e.g. "50,75,95,99" (pass null for the default)')->nullable()->required(),
            'range' => $schema->string()->description('Named relative window, e.g. this_month | last_7_days (pass null to use from/to)')->nullable()->required(),
            'timezone_offset' => $schema->integer()->description('Timezone as minutes east of UTC, e.g. 540 for Tokyo (pass null for UTC)')->nullable()->required(),
            'from' => $schema->string()->description('ISO start date, inclusive — ignored when range is set (pass null for 30 days ago)')->nullable()->required(),
            'to' => $schema->string()->description('ISO end date, exclusive — ignored when range is set (pass null for now)')->nullable()->required(),
            'filters' => $schema->string()->description('Filter expression, same DSL as the search tool — scopes this widget to a slice of the window; for a funnel or A-vs-B, call once per slice with a different filter (pass null for none)')->nullable()->required(),
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
            default => throw new InvalidArgumentException(sprintf("Unknown widget '%s'. Use one of: kpi, kpi_delta, trend, cumulative, grouped_trend, breakdown, distribution, percentiles.", $widget)),
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
