<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

/**
 * A top-N ranked list — a dimension ranked by a metric ("top products by revenue",
 * "top issues by event count").
 */
class Breakdown extends Widget
{
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected string $groupBy,
        protected Metric $metric,
        protected string $field,
        protected int $limit,
        protected string $direction,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $aggs->terms('groups', $this->groupBy)
                ->size($this->limit)
                ->order($this->metric->orderKey('metric'), $this->direction)
                ->aggregate(fn (Aggs $sub) => $this->metric->apply($sub, 'metric', $this->field));
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $groups = new Collection($aggregations[$this->name]['groups']['buckets'] ?? []);

        return [
            'type' => 'breakdown',
            'metric' => $this->metric->value,
            'field' => $this->field,
            'group_by' => $this->groupBy,
            'rows' => $groups->map(fn (array $bucket): array => [
                'key' => $bucket['key'],
                'value' => $this->metric->extract($bucket['metric'] ?? []),
                'count' => $bucket['doc_count'] ?? 0,
            ])->toArray(),
        ];
    }
}
