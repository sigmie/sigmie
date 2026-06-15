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
use Sigmie\Analytics\Widgets\Funnel;
use Sigmie\Analytics\Widgets\Geo;
use Sigmie\Analytics\Widgets\GroupedTrend;
use Sigmie\Analytics\Widgets\Heatmap;
use Sigmie\Analytics\Widgets\Kpi;
use Sigmie\Analytics\Widgets\KpiDelta;
use Sigmie\Analytics\Widgets\MultiBreakdown;
use Sigmie\Analytics\Widgets\Percentiles;
use Sigmie\Analytics\Widgets\Retention;
use Sigmie\Analytics\Widgets\StatSummary;
use Sigmie\Analytics\Widgets\Table;
use Sigmie\Analytics\Widgets\Trend;
use Sigmie\Analytics\Widgets\Widget;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\FilterParser;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Aggs;
use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\NewQuery;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\Query;
use Sigmie\Query\Search;
use Sigmie\Search\Contracts\MultiSearchable;

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
 *
 * Implements {@see MultiSearchable}, so a whole dashboard can be added to a {@see newMultiSearch()}
 * (via newAnalytics()) and batched into one _msearch alongside other queries.
 */
class Analytics implements MultiSearchable
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

    protected ?Search $compiledSearch = null;

    protected ?Properties $resolvedProperties = null;

    /**
     * @var list<callable(Search): void>
     */
    protected array $searchCallbacks = [];

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
     * Customize the underlying Elasticsearch search used for this dashboard. This lets callers fetch
     * document hits and aggregations from the same request by overriding the default size(0), adding a
     * post_filter for rows, sorting, collapsing, tracking totals, or attaching raw search options.
     */
    public function search(callable $callback): static
    {
        $this->searchCallbacks[] = $callback;
        $this->compiledSearch = null;

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

    /**
     * Add a KPI widget. An optional per-widget $filter — a filter-DSL string ("status:'paid'") or a
     * query object — restricts this KPI to its own slice of the window, so a single query can hold a
     * whole funnel ("total", "engaged", "completed") of KPIs each counting a different slice. An
     * optional $window — a named {@see Period} or a [from, to] pair — scopes this widget to its own
     * time window instead of the analytics-wide one (an all-time headline next to a recent trend).
     */
    public function kpi(string $as, Metric $metric, string $field = '', Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        return $this->addFiltered(new Kpi($as, $this->dateField, $from, $to, $this->dateFormat, $metric, $this->metricField($metric, $field)), $filter);
    }

    public function kpiDelta(string $as, Metric $metric, string $field = '', Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);
        [$previousFrom, $previousTo] = $this->previousWindow($from, $to, $window === null);

        return $this->addFiltered(new KpiDelta($as, $this->dateField, $from, $to, $this->dateFormat, $metric, $this->metricField($metric, $field), $previousFrom, $previousTo), $filter);
    }

    public function trend(string $as, Metric $metric, string $field = '', CalendarInterval|string $interval = CalendarInterval::Day, Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        return $this->addFiltered(new Trend($as, $this->dateField, $from, $to, $this->dateFormat, $metric, $this->metricField($metric, $field), $interval, $this->timeZone), $filter);
    }

    public function cumulative(string $as, Metric $metric, string $field = '', CalendarInterval|string $interval = CalendarInterval::Day, Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        return $this->addFiltered(new Cumulative($as, $this->dateField, $from, $to, $this->dateFormat, $metric, $this->metricField($metric, $field), $interval, $this->timeZone), $filter);
    }

    public function groupedTrend(string $as, Metric $metric, string $field, string $groupBy, CalendarInterval|string $interval = CalendarInterval::Day, int $limit = 5, Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        return $this->addFiltered(new GroupedTrend($as, $this->dateField, $from, $to, $this->dateFormat, $metric, $this->metricField($metric, $field), $this->aggregatableField($groupBy), $interval, $limit, $this->timeZone), $filter);
    }

    public function breakdown(string $as, string $groupBy, Metric $metric, string $field = '', int $limit = 10, string $direction = 'desc', Query|string|null $filter = null, Period|array|null $window = null, array $bucketAliases = []): static
    {
        [$from, $to] = $this->resolveWindow($window);

        return $this->addFiltered(new Breakdown($as, $this->dateField, $from, $to, $this->dateFormat, $this->aggregatableField($groupBy), $metric, $this->metricField($metric, $field), $limit, $direction, $bucketAliases), $filter);
    }

    /**
     * Add a top-N ranked list over a composite key, such as product + channel by revenue.
     *
     * @param  list<string>  $groupBy
     */
    public function multiBreakdown(string $as, array $groupBy, Metric $metric, string $field = '', int $limit = 10, string $direction = 'desc', Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        $resolvedGroupBy = array_values(array_map(
            fn (string $field): string => $this->aggregatableField($field),
            $groupBy,
        ));

        return $this->addFiltered(new MultiBreakdown($as, $this->dateField, $from, $to, $this->dateFormat, $resolvedGroupBy, $metric, $this->metricField($metric, $field), $limit, $direction), $filter);
    }

    public function distribution(string $as, string $field, int $interval, Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        return $this->addFiltered(new Distribution($as, $this->dateField, $from, $to, $this->dateFormat, $this->aggregatableField($field), $interval), $filter);
    }

    /**
     * @param  list<int|float>  $percents
     */
    public function percentiles(string $as, string $field, array $percents = [50, 75, 95, 99], Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        return $this->addFiltered(new Percentiles($as, $this->dateField, $from, $to, $this->dateFormat, $this->aggregatableField($field), $percents), $filter);
    }

    /**
     * Add a table of the actual matching documents — the rows behind a number, not a metric. $fields
     * limits the returned source (empty returns the full document); $sort is a "field:dir" string
     * ("amount:desc", defaults to descending) sorting the rows before $limit is applied.
     */
    public function table(string $as, array $fields = [], int $limit = 10, ?string $sort = null, Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        return $this->addFiltered(new Table($as, $this->dateField, $from, $to, $this->dateFormat, $fields, $limit, $this->sortClause($sort)), $filter);
    }

    /**
     * Add the five-number summary (count, min, max, avg, sum) of a numeric field.
     */
    public function stats(string $as, string $field, Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        return $this->addFiltered(new StatSummary($as, $this->dateField, $from, $to, $this->dateFormat, $this->aggregatableField($field)), $filter);
    }

    /**
     * Add an ordered step funnel. $steps is an ordered map of label => slice, where each slice is a
     * filter-DSL string ("event:'signup'") or a query object; the result reports each step's count
     * and its conversion against both the first and the previous step.
     *
     * @param  array<string, Query|string>  $steps
     */
    public function funnel(string $as, array $steps, Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        $resolved = [];

        foreach ($steps as $label => $step) {
            $resolved[] = ['label' => (string) $label, 'query' => $this->resolveQuery($step)];
        }

        return $this->addFiltered(new Funnel($as, $this->dateField, $from, $to, $this->dateFormat, $resolved), $filter);
    }

    /**
     * Add a two-dimensional matrix — $rowField by $colField, each cell carrying $metric of $field.
     * Defaults to a count of documents per cell.
     */
    public function heatmap(string $as, string $rowField, string $colField, Metric $metric = Metric::Count, string $field = '', int $rowLimit = 10, int $colLimit = 10, Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        return $this->addFiltered(new Heatmap($as, $this->dateField, $from, $to, $this->dateFormat, $this->aggregatableField($rowField), $this->aggregatableField($colField), $metric, $this->metricField($metric, $field), $rowLimit, $colLimit), $filter);
    }

    /**
     * Add a cohort retention grid — entities (identified by $idField) grouped by the period of their
     * $cohortField date, counted distinct across each later $dateField period.
     */
    public function retention(string $as, string $cohortField, string $idField, CalendarInterval|string $interval = CalendarInterval::Day, Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        return $this->addFiltered(new Retention($as, $this->dateField, $from, $to, $this->dateFormat, $cohortField, $this->aggregatableField($idField), $interval, $this->timeZone), $filter);
    }

    /**
     * Add a map heatmap — $metric bucketed into geohash cells of a geo_point $field. Higher
     * $precision means smaller, more granular cells. Defaults to a count of documents per cell.
     */
    public function geo(string $as, string $field, int $precision = 5, ?int $size = null, Metric $metric = Metric::Count, string $metricField = '', Query|string|null $filter = null, Period|array|null $window = null): static
    {
        [$from, $to] = $this->resolveWindow($window);

        return $this->addFiltered(new Geo($as, $this->dateField, $from, $to, $this->dateFormat, $field, $precision, $size, $metric, $this->metricField($metric, $metricField)), $filter);
    }

    /**
     * Turn a "field:dir" string into an Elasticsearch sort clause, resolving the field to its
     * aggregatable path. The direction is optional and defaults to descending.
     */
    protected function sortClause(?string $sort): ?array
    {
        if ($sort === null) {
            return null;
        }

        [$field, $direction] = array_pad(explode(':', $sort, 2), 2, 'desc');

        return [[$this->aggregatableField($field) => ['order' => $direction]]];
    }

    /**
     * Resolve a per-step slice to a query: a query object as-is, or a filter-DSL string parsed with
     * the same {@see FilterParser} as the analytics-wide filters().
     */
    protected function resolveQuery(Query|string $query): Query
    {
        return $query instanceof Query ? $query : (new FilterParser($this->properties))->parse($query);
    }

    /**
     * Register a widget, first attaching an optional per-widget filter — a filter-DSL string
     * ("status:'paid'") or a query object — that scopes this widget to its own slice of the window.
     * A string is parsed with the same {@see FilterParser} as the analytics-wide filters().
     */
    protected function addFiltered(Widget $widget, Query|string|null $filter): static
    {
        if ($filter !== null) {
            $resolved = $filter instanceof Query ? $filter : (new FilterParser($this->properties))->parse($filter);

            $widget->filter($resolved);
        }

        return $this->add($widget);
    }

    /**
     * Run every registered widget in one request and return a map of widget name => normalised result.
     */
    public function get(): array
    {
        return $this->mapWidgets($this->run());
    }

    /**
     * Run the analytics search and return both normalized widgets and the raw Elasticsearch hits block.
     * Use {@see Search()} to configure hit pagination, sorting, collapse, post_filter, and total hits.
     *
     * @return array{widgets: array<string, mixed>, hits: array<string, mixed>, took: int|null, timed_out: bool}
     */
    public function getWithHits(): array
    {
        $response = $this->compile()->response()->json() ?? [];

        return $this->formatResponseWithHits($response);
    }

    /**
     * Turn a raw search response into the map of widget name => normalised result. Public so a
     * multi-search caller that batched {@see toSearch()} into one _msearch can format that response
     * slot directly, instead of going through {@see get()} (which runs its own request).
     */
    public function formatResponse(array $response): array
    {
        return $this->mapWidgets($response['aggregations'] ?? []);
    }

    /**
     * Format a raw search response when the analytics request also fetches document hits.
     *
     * @return array{widgets: array<string, mixed>, hits: array<string, mixed>, took: int|null, timed_out: bool}
     */
    public function formatResponseWithHits(array $response): array
    {
        return [
            'widgets' => $this->formatResponse($response),
            'hits' => $response['hits'] ?? [],
            'took' => $response['took'] ?? null,
            'timed_out' => $response['timed_out'] ?? false,
        ];
    }

    /**
     * The whole dashboard as a single MultiSearchable query — size(0), the analytics-wide filter, and
     * every widget's aggregations. Add it to a newMultiSearch() to batch the dashboard into one
     * _msearch round-trip alongside other searches (e.g. a paginated rows query that can't fold into
     * aggregations), then feed the matching response slot back through {@see formatResponse()}.
     *
     *   $multi = $sigmie->newMultiSearch();
     *   $multi->addQuery($analytics->toSearch(), 'metrics');
     *   $multi->newSearch($index, 'rows')->queryString('…')->size(20);
     *   [$metrics, $rows] = $multi->get();
     *   $dashboard = $analytics->formatResponse($metrics);
     */
    public function toSearch(): NewQuery
    {
        $this->compile();

        return $this->query;
    }

    /**
     * {@see MultiSearchable}: the _msearch header + body for this dashboard, so newMultiSearch() can
     * batch it. The response slot comes back through {@see formatResponses()} as the widget map.
     */
    public function toMultiSearch(): array
    {
        return $this->toSearch()->toMultiSearch();
    }

    public function multisearchResCount(): int
    {
        return 1;
    }

    public function formatResponses(...$responses): array
    {
        return $this->formatResponse($responses[0] ?? []);
    }

    protected function run(): array
    {
        return $this->compile()->response()->json('aggregations') ?? [];
    }

    protected function mapWidgets(array $aggregations): array
    {
        $result = [];

        foreach ($this->widgets as $name => $widget) {
            $result[$name] = $widget->extract($aggregations);
        }

        return $result;
    }

    /**
     * Configure the underlying search once and memoise it, so the single-request {@see run()} and the
     * multi-search {@see toSearch()} paths share one body and the widget aggregations are added once.
     */
    protected function compile(): Search
    {
        if ($this->compiledSearch instanceof Search) {
            return $this->compiledSearch;
        }

        $search = $this->query
            ->properties($this->properties)
            ->bool(function (Boolean $boolean): void {
                $filter = $boolean->filter();

                $this->filters !== '' ? $filter->parse($this->filters) : $filter->matchAll();

                foreach ($this->filterQueries as $query) {
                    $filter->query($query);
                }
            })
            ->size(0)
            ->aggregate(function (Aggs $aggs): void {
                foreach ($this->widgets as $widget) {
                    $aggs->add($widget);
                }
            });

        foreach ($this->searchCallbacks as $callback) {
            $callback($search);
        }

        return $this->compiledSearch = $search;
    }

    /**
     * Resolve an optional per-widget window override to a [from, to] pair, falling back to the
     * analytics-wide window. A {@see Period} is resolved in the configured timezone (so the query stays
     * cacheable); a [from, to] array of DateTimeInterface is taken as-is.
     *
     * @param  Period|array{0: DateTimeInterface, 1: DateTimeInterface}|null  $window
     * @return array{0: DateTimeInterface, 1: DateTimeInterface}
     */
    protected function resolveWindow(Period|array|null $window): array
    {
        if ($window === null) {
            return [$this->from, $this->to];
        }

        if ($window instanceof Period) {
            return $window->resolve(new DateTimeImmutable('now', new DateTimeZone($this->timeZone ?? 'UTC')));
        }

        return $window;
    }

    /**
     * The window to compare a kpiDelta against: the previous instance of a named calendar period
     * (this month → last month) when the widget uses the analytics-wide range, or the immediately
     * preceding equal-duration window otherwise (including any per-widget window override).
     *
     * @return array{0: DateTimeInterface, 1: DateTimeInterface} [previousFrom, previousTo]
     */
    protected function previousWindow(DateTimeInterface $from, DateTimeInterface $to, bool $usePeriod): array
    {
        $previousTo = DateTimeImmutable::createFromInterface($from);

        $modifier = $usePeriod ? $this->period?->previousModifier() : null;

        if ($modifier !== null) {
            return [$previousTo->modify($modifier), $previousTo];
        }

        $length = $to->getTimestamp() - $from->getTimestamp();

        return [$previousTo->setTimestamp($from->getTimestamp() - $length), $previousTo];
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
