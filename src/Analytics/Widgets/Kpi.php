<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;

/**
 * A single scalar number for the period — the "big number" on a dashboard
 * (gross volume, total errors, distinct users…).
 */
class Kpi extends Widget
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
        return $this->scoped(
            $this->name,
            $this->from,
            $this->to,
            fn (Aggs $aggs) => $this->metric->apply($aggs, 'metric', $this->field),
        )->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $bucket = $aggregations[$this->name] ?? [];

        return [
            'type' => 'kpi',
            'metric' => $this->metric->value,
            'field' => $this->field,
            'value' => $this->metric->extract($bucket['metric'] ?? []),
            'count' => $bucket['doc_count'] ?? 0,
        ];
    }
}
