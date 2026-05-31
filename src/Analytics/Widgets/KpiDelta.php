<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;

/**
 * A KPI with a period-over-period comparison — the value for [from, to) against the
 * preceding window [previousFrom, previousTo), plus the percentage change
 * ("$12.3k, +12% vs last month"). The previous window is supplied by the builder, so it can be
 * calendar-aware (last month) when a named period is used, or equal-duration otherwise.
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
        $metric = fn (Aggs $aggs) => $this->metric->apply($aggs, 'metric', $this->field);

        return [
            ...$this->scoped($this->name.'_current', $this->from, $this->to, $metric)->toRaw(),
            ...$this->scoped($this->name.'_previous', $this->previousFrom, $this->previousTo, $metric)->toRaw(),
        ];
    }

    public function extract(array $aggregations): array
    {
        $current = $this->metric->extract($aggregations[$this->name.'_current']['metric'] ?? []);
        $previous = $this->metric->extract($aggregations[$this->name.'_previous']['metric'] ?? []);

        $change = ($previous !== null && $previous != 0.0 && $current !== null)
            ? round((($current - $previous) / $previous) * 100, 2)
            : null;

        return [
            'type' => 'kpi_delta',
            'metric' => $this->metric->value,
            'field' => $this->field,
            'value' => $current,
            'previous' => $previous,
            'change_pct' => $change,
        ];
    }
}
