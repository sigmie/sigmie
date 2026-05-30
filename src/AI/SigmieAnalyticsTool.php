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
            ."- percentiles: p50/p75/p95/p99 of a numeric field (needs field)";

        $description .= "\n\nMetrics (`metric`): sum, avg, min, max, count, unique (distinct), median.";
        $description .= "\n\nIntervals (`interval`): minute, hour, day, week, month, quarter, year.";

        $description .= "\n\nTimeline fields for `date_field`: ".(
            $dateFields !== [] ? implode(', ', $dateFields) : '(none — this index has no date field)'
        );
        $description .= "\nNumeric fields for `field`: ".(
            $numericFields !== [] ? implode(', ', $numericFields) : '(none)'
        );

        return $description."\n\n"
            ."Time window: `from` and `to` are ISO dates (default: last 30 days).\n"
            .'Narrow with `filters` (same DSL as the search tool, e.g. "status:\'paid\' AND amount>10"). Call describe_index for the full field list and filter syntax.';
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
            'interval' => $schema->string()->description('Time bucket for trends: minute | hour | day | week | month | quarter | year (default day)')->default('day')->nullable()->required(),
            'group_by' => $schema->string()->description('Keyword field to group/break down by, for breakdown and grouped_trend (pass null otherwise)')->nullable()->required(),
            'limit' => $schema->integer()->description('Max groups for breakdown/grouped_trend (default 10)')->default(10)->nullable()->required(),
            'bucket_size' => $schema->integer()->description('Bucket width for the distribution widget (pass null otherwise)')->nullable()->required(),
            'percents' => $schema->string()->description('Comma-separated percentiles for the percentiles widget, e.g. "50,75,95,99" (pass null for the default)')->nullable()->required(),
            'from' => $schema->string()->description('ISO start date, inclusive (pass null for 30 days ago)')->nullable()->required(),
            'to' => $schema->string()->description('ISO end date, exclusive (pass null for now)')->nullable()->required(),
            'filters' => $schema->string()->description('Filter expression, same DSL as the search tool (pass null for none)')->nullable()->required(),
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
        $interval = fn (): CalendarInterval => $this->interval((string) ($request['interval'] ?? 'day'));
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

    protected function interval(string $interval): CalendarInterval
    {
        return match (strtolower(trim($interval))) {
            'minute' => CalendarInterval::Minute,
            'hour' => CalendarInterval::Hour,
            '', 'day' => CalendarInterval::Day,
            'week' => CalendarInterval::Week,
            'month' => CalendarInterval::Month,
            'quarter' => CalendarInterval::Quarter,
            'year' => CalendarInterval::Year,
            default => throw new InvalidArgumentException(sprintf(
                "Unknown interval '%s'. Use one of: minute, hour, day, week, month, quarter, year.",
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
