<?php

declare(strict_types=1);

namespace Sigmie\Analytics;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Analytics\Enums\Period;
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
use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\NewQuery;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\Query;

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

    /**
     * @var list<QueryClause>
     */
    protected array $filterQueries = [];

    protected DateTimeInterface $from;

    protected DateTimeInterface $to;

    protected string $dateFormat = DateTimeInterface::ATOM;

    protected ?string $timeZone = null;

    protected ?Period $period = null;

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

    /**
     * The index mapping, so the filter DSL is typed and keyword-like fields resolve to their
     * `.keyword` aggregatable path. Set automatically by SigmieIndex::analytics(); pass it
     * explicitly when entering through Sigmie::analytics().
     */
    public function properties(NewProperties $properties): static
    {
        $this->properties = $properties;
        $this->resolvedProperties = null;

        return $this;
    }

    public function filters(string $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * AND a hard query clause into every widget's filter context, alongside the string filters().
     * Mirrors NewSearch::filterQuery() for callers composing a query object instead of the DSL.
     */
    public function filterQuery(QueryClause $query): static
    {
        $this->filterQueries[] = $query;

        return $this;
    }

    /**
     * Set the timezone as an offset in minutes east of UTC (Tokyo = 540, New York = -300, India = 330).
     * From a browser, pass `-new Date().getTimezoneOffset()`. Aligns calendar buckets and named
     * ranges to local time. For DST-correctness across a transition, prefer {@see timezone()}.
     */
    public function timezoneOffset(int $minutes): static
    {
        $sign = $minutes < 0 ? '-' : '+';
        $abs = abs($minutes);

        return $this->timezone(sprintf('%s%02d:%02d', $sign, intdiv($abs, 60), $abs % 60));
    }

    /**
     * Set the timezone as an Elasticsearch time zone — an IANA name ('Asia/Tokyo', DST-correct)
     * or a fixed offset ('+09:00').
     */
    public function timezone(string $timeZone): static
    {
        $this->timeZone = $timeZone;

        return $this;
    }

    /**
     * Set the window to a named relative period ("this month", "last 7 days"), resolved in the
     * configured timezone. A calendar period also makes a kpiDelta compare against the previous
     * instance of that period (this month vs last month).
     */
    public function range(Period $period): static
    {
        [$this->from, $this->to] = $period->resolve(new DateTimeImmutable('now', new DateTimeZone($this->timeZone ?? 'UTC')));
        $this->period = $period;

        return $this;
    }

    public function kpi(string $as, Metric $metric, string $field = '', ?Query $filter = null): static
    {
        $kpi = new Kpi($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $metric, $this->metricField($metric, $field));

        if ($filter !== null) {
            $kpi->filter($filter);
        }

        return $this->add($kpi);
    }

    public function kpiDelta(string $as, Metric $metric, string $field = ''): static
    {
        [$previousFrom, $previousTo] = $this->previousWindow();

        return $this->add(new KpiDelta($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $metric, $this->metricField($metric, $field), $previousFrom, $previousTo));
    }

    public function trend(string $as, Metric $metric, string $field = '', CalendarInterval|string $interval = CalendarInterval::Day): static
    {
        return $this->add(new Trend($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $metric, $this->metricField($metric, $field), $interval, $this->timeZone));
    }

    public function cumulative(string $as, Metric $metric, string $field = '', CalendarInterval|string $interval = CalendarInterval::Day): static
    {
        return $this->add(new Cumulative($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $metric, $this->metricField($metric, $field), $interval, $this->timeZone));
    }

    public function groupedTrend(string $as, Metric $metric, string $field, string $groupBy, CalendarInterval|string $interval = CalendarInterval::Day, int $limit = 5): static
    {
        return $this->add(new GroupedTrend($as, $this->dateField, $this->from, $this->to, $this->dateFormat, $metric, $this->metricField($metric, $field), $this->aggregatableField($groupBy), $interval, $limit, $this->timeZone));
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
        $filterQueries = $this->filterQueries;

        $search = $this->query
            ->properties($this->properties)
            ->bool(function (Boolean $boolean) use ($filters, $filterQueries): void {
                $filter = $boolean->filter();

                $filters !== '' ? $filter->parse($filters) : $filter->matchAll();

                foreach ($filterQueries as $query) {
                    $filter->query($query);
                }
            })
            ->size(0)
            ->aggregate(function (Aggs $aggs): void {
                foreach ($this->widgets as $widget) {
                    $aggs->add($widget);
                }
            });

        return $search->response()->json('aggregations') ?? [];
    }

    /**
     * The window to compare a kpiDelta against: the previous instance of a named calendar period
     * (this month → last month), or the immediately preceding equal-duration window otherwise.
     *
     * @return array{0: DateTimeInterface, 1: DateTimeInterface} [previousFrom, previousTo]
     */
    protected function previousWindow(): array
    {
        $previousTo = DateTimeImmutable::createFromInterface($this->from);

        $modifier = $this->period?->previousModifier();

        if ($modifier !== null) {
            return [$previousTo->modify($modifier), $previousTo];
        }

        $length = $this->to->getTimestamp() - $this->from->getTimestamp();

        return [$previousTo->setTimestamp($this->from->getTimestamp() - $length), $previousTo];
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
