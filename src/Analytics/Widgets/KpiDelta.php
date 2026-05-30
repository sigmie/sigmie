<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeImmutable;
use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;

/**
 * A KPI with a period-over-period comparison — the value for [from, to) against the
 * immediately preceding window of equal length, plus the percentage change
 * ("$12.3k, +12% vs last month").
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
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        $length = $this->to->getTimestamp() - $this->from->getTimestamp();
        $previousFrom = (new DateTimeImmutable)->setTimestamp($this->from->getTimestamp() - $length);

        $metric = fn (Aggs $aggs) => $this->metric->apply($aggs, 'metric', $this->field);

        return [
            ...$this->scoped("{$this->name}_current", $this->from, $this->to, $metric)->toRaw(),
            ...$this->scoped("{$this->name}_previous", $previousFrom, $this->from, $metric)->toRaw(),
        ];
    }

    public function extract(array $aggregations): array
    {
        $current = $this->metric->extract($aggregations["{$this->name}_current"]['metric'] ?? []);
        $previous = $this->metric->extract($aggregations["{$this->name}_previous"]['metric'] ?? []);

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
