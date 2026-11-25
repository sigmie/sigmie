<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggregations\Bucket\Filter;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\MatchAll;

/**
 * A KPI with a period-over-period comparison — the value for [from, to) against the
 * preceding window [previousFrom, previousTo), plus the percentage change
 * ("$12.3k, +12% vs last month"). The previous window is supplied by the builder, so it can be
 * calendar-aware (last month) when a named period is used, or equal-duration otherwise.
 *
 * The previous filter bucket is a sibling of the current one. A child of the current
 * date filter matches no document in the previous window, so a per-widget filter copied
 * onto that inner bucket never applies there.
 */
class KpiDelta extends Widget
{
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected Metric $metric,
        protected string $field,
        protected DateTimeInterface $previousFrom,
        protected DateTimeInterface $previousTo,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        $metric = fn (Aggs $aggs) => $this->metric->apply($aggs, 'metric', $this->field, $this->countedField());
        $current = $this->scoped('current', $this->from, $this->to, $metric);
        $previous = $this->scoped('previous', $this->previousFrom, $this->previousTo, $metric);

        return (new Filter($this->name, $this->filter ?? new MatchAll))
            ->aggregate(function (Aggs $aggs) use ($current, $previous): void {
                $aggs->add($current);
                $aggs->add($previous);
            })
            ->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $bucket = $aggregations[$this->name] ?? [];
        $current = $this->metric->extract($bucket['current']['metric'] ?? []);
        $previous = $this->metric->extract($bucket['previous']['metric'] ?? []);

        $change = null;
        if ($previous !== null && $previous != 0.0 && $current !== null) {
            $difference = $current - $previous;
            $ratio = is_finite($difference) ? $difference / $previous : $current / $previous - 1;
            $percentage = $ratio * 100;
            $change = is_finite($percentage) ? round($percentage, 2) : null;
        }

        return [
            'type' => 'kpi_delta',
            'metric' => $this->metric->value,
            'field' => $this->metric->resultField($this->field, $this->countedField()),
            'value' => $current,
            'previous' => $previous,
            'change_pct' => $change,
        ];
    }
}
