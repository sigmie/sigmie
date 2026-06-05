<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

/**
 * A two-dimensional matrix — one dimension by another, each cell carrying a metric
 * ("country × device by revenue", "status × priority by count"). Backed by nested `terms`
 * buckets with a metric sub-aggregation on each cell.
 */
class Heatmap extends Widget
{
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected string $rowField,
        protected string $colField,
        protected Metric $metric,
        protected string $field,
        protected int $rowLimit,
        protected int $colLimit,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $aggs->terms('rows', $this->rowField)
                ->size($this->rowLimit)
                ->aggregate(function (Aggs $sub): void {
                    $sub->terms('cols', $this->colField)
                        ->size($this->colLimit)
                        ->aggregate(fn (Aggs $m) => $this->metric->apply($m, 'metric', $this->field));
                });
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $rows = new Collection($aggregations[$this->name]['rows']['buckets'] ?? []);

        return [
            'type' => 'heatmap',
            'metric' => $this->metric->value,
            'field' => $this->field,
            'row_field' => $this->rowField,
            'col_field' => $this->colField,
            'rows' => $rows->map(fn (array $row): array => [
                'key' => $row['key'],
                'count' => $row['doc_count'] ?? 0,
                'cells' => array_map(fn (array $cell): array => [
                    'key' => $cell['key'],
                    'value' => $this->metric->extract($cell['metric'] ?? []),
                    'count' => $cell['doc_count'] ?? 0,
                ], $row['cols']['buckets'] ?? []),
            ])->toArray(),
        ];
    }
}
