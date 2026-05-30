<?php

declare(strict_types=1);

namespace Sigmie\Analytics;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Analytics\Widgets\Breakdown;
use Sigmie\Analytics\Widgets\Cumulative;
use Sigmie\Analytics\Widgets\Distribution;
use Sigmie\Analytics\Widgets\GroupedTrend;
use Sigmie\Analytics\Widgets\Kpi;
use Sigmie\Analytics\Widgets\KpiDelta;
use Sigmie\Analytics\Widgets\Percentiles;
use Sigmie\Analytics\Widgets\Trend;
use Sigmie\Analytics\Widgets\Widget;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Aggs;
use Sigmie\Query\NewQuery;
use Sigmie\Query\Queries\Compound\Boolean;

/**
 * Dashboard analytics for an index. Compose one or more widgets — KPIs, trends, breakdowns,
 * distributions — over a time window and an optional filter, then {@see get()} them all in a
 * single Elasticsearch request.
 *
 *   $index->analytics('created_at')
 *       ->from($start)->to($end)
 *       ->filters("status:'paid'")
 *       ->trend('revenue', Metric::Sum, 'amount', CalendarInterval::Day)
 *       ->breakdown('top_products', 'product_id', Metric::Sum, 'amount', limit: 5)
 *       ->get();
 *
 * "Per day → per month" is the same call with a different {@see CalendarInterval}.
 */
class Analytics
{
    /**
     * @var array<string, Widget>
     */
    protected array $widgets = [];

    protected string $filters = '';

    protected DateTimeInterface $from;

    protected DateTimeInterface $to;

    protected string $dateFormat = DateTimeInterface::ATOM;

    protected ?Properties $resolvedProperties = null;

    public function __construct(
        protected NewQuery $query,
        protected NewProperties $properties,
        protected string $dateField,
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
    ) {
        $this->to = $to ?? new DateTimeImmutable;
        $this->from = $from ?? (new DateTimeImmutable)->setTimestamp($this->to->getTimestamp())->sub(new DateInterval('P30D'));
    }

    public function from(DateTimeInterface $from): static
    {
        $this->from = $from;

        return $this;
    }

    public function to(DateTimeInterface $to): static
    {
        $this->to = $to;

        return $this;
    }

    public function format(string $dateFormat): static
    {
        $this->dateFormat = $dateFormat;

        return $this;
    }

    public function filters(string $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function kpi(string $as, Metric $metric, string $field = ''): static
    {
        return $this->add(new Kpi($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $metric, $this->metricField($metric, $field)));
    }

    public function kpiDelta(string $as, Metric $metric, string $field = ''): static
    {
        return $this->add(new KpiDelta($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $metric, $this->metricField($metric, $field)));
    }

    public function trend(string $as, Metric $metric, string $field = '', CalendarInterval $interval = CalendarInterval::Day): static
    {
        return $this->add(new Trend($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $metric, $this->metricField($metric, $field), $interval));
    }

    public function cumulative(string $as, Metric $metric, string $field = '', CalendarInterval $interval = CalendarInterval::Day): static
    {
        return $this->add(new Cumulative($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $metric, $this->metricField($metric, $field), $interval));
    }

    public function groupedTrend(string $as, Metric $metric, string $field, string $groupBy, CalendarInterval $interval = CalendarInterval::Day, int $limit = 5): static
    {
        return $this->add(new GroupedTrend($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $metric, $this->metricField($metric, $field), $this->aggregatableField($groupBy), $interval, $limit));
    }

    public function breakdown(string $as, string $groupBy, Metric $metric, string $field = '', int $limit = 10, string $direction = 'desc'): static
    {
        return $this->add(new Breakdown($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $this->aggregatableField($groupBy), $metric, $this->metricField($metric, $field), $limit, $direction));
    }

    public function distribution(string $as, string $field, int $interval): static
    {
        return $this->add(new Distribution($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $this->aggregatableField($field), $interval));
    }

    /**
     * @param  list<int|float>  $percents
     */
    public function percentiles(string $as, string $field, array $percents = [50, 75, 95, 99]): static
    {
        return $this->add(new Percentiles($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $this->aggregatableField($field), $percents));
    }

    /**
     * Run every registered widget in one request and return a map of widget name => normalised result.
     */
    public function get(): array
    {
        $aggregations = $this->run();

        $result = [];

        foreach ($this->widgets as $name => $widget) {
            $result[$name] = $widget->extract($aggregations);
        }

        return $result;
    }

    protected function run(): array
    {
        $filters = $this->filters;

        $search = $this->query
            ->properties($this->properties)
            ->bool(function (Boolean $boolean) use ($filters): void {
                $filters !== ''
                    ? $boolean->filter()->parse($filters)
                    : $boolean->filter()->matchAll();
            })
            ->size(0)
            ->aggregate(function (Aggs $aggs): void {
                foreach ($this->widgets as $widget) {
                    $aggs->add($widget);
                }
            });

        return $search->response()->json('aggregations') ?? [];
    }

    protected function add(Widget $widget): static
    {
        $this->widgets[$widget->name()] = $widget;

        return $this;
    }

    /**
     * Count has no meaningful metric field, so it falls back to the timeline field
     * (every event has a timestamp) when none is given.
     */
    protected function metricField(Metric $metric, string $field): string
    {
        if ($field === '') {
            return $metric === Metric::Count ? $this->dateField : $field;
        }

        return $this->aggregatableField($field);
    }

    /**
     * Resolve a field to the name Elasticsearch can aggregate/sort on: keyword-like text fields
     * (e.g. a category) aggregate on their `.keyword` sub-field, everything else on itself.
     */
    protected function aggregatableField(string $field): string
    {
        if ($field === '') {
            return $field;
        }

        $this->resolvedProperties ??= $this->properties->get();

        $type = $this->resolvedProperties->get($field);

        if ($type instanceof Text) {
            return $type->keywordName() ?? $field;
        }

        return $field;
    }
}
