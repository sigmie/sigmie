<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Query\Aggregations\Bucket\Filter;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\Query;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Shared\Contracts\ToRaw;

/**
 * A dashboard widget: a self-contained chunk of analytics (a KPI, a trend, a breakdown…)
 * that knows how to render itself as Elasticsearch aggregations ({@see ToRaw()}) and how to
 * normalise the response into a chart-ready shape ({@see extract()}).
 *
 * Every widget scopes itself to its own time window with a `filter` bucket via {@see scoped()},
 * so widgets with different windows (e.g. a period-over-period delta) can share one query.
 */
abstract class Widget implements ToRaw
{
    /**
     * An extra hard filter ANDed into this widget's own `filter` bucket, alongside its time window.
     */
    protected ?Query $filter = null;

    public function __construct(
        protected string $name,
        protected string $dateField,
        protected DateTimeInterface $from,
        protected DateTimeInterface $to,
        protected string $dateFormat,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Restrict this widget to a slice of the window. Unlike the analytics-wide filters()/filterQuery(),
     * this applies to this widget only, so a single query can hold widgets counting different slices —
     * e.g. a funnel of "total", "engaged" and "completed" KPIs over the same time window.
     */
    public function filter(Query $filter): static
    {
        $this->filter = $filter;

        return $this;
    }

    abstract public function toRaw(): array;

    abstract public function extract(array $aggregations): array;

    /**
     * Force a date histogram to span the FULL requested window [$from, $to), emitting zero-count
     * buckets for periods with no data. Without this the histogram stops at the last bucket that
     * has documents, so a query for a wide range (e.g. a full year over partial data) silently
     * trims the empty tail instead of showing it. Epoch millis; max is exclusive-aware.
     *
     * @return array{min: int, max: int}
     */
    protected function extendedBounds(): array
    {
        return [
            'min' => $this->from->getTimestamp() * 1000,
            'max' => $this->to->getTimestamp() * 1000 - 1,
        ];
    }

    /**
     * Wrap the inner aggregations in a `filter` bucket scoped to [$from, $to).
     */
    protected function scoped(
        string $name,
        DateTimeInterface $from,
        DateTimeInterface $to,
        callable $inner,
    ): Filter {
        $range = new Range($this->dateField, [
            '>=' => $from->format($this->dateFormat),
            '<' => $to->format($this->dateFormat),
        ]);

        return (new Filter($name, $this->scopedQuery($range)))->aggregate($inner);
    }

    /**
     * The query for this widget's filter bucket: just the time range, or the time range ANDed
     * with the per-widget {@see filter()} when one is set.
     */
    private function scopedQuery(Range $range): Query
    {
        if ($this->filter === null) {
            return $range;
        }

        $boolean = new Boolean;
        $boolean->must()->query($range);
        $boolean->must()->query($this->filter);

        return $boolean;
    }
}
