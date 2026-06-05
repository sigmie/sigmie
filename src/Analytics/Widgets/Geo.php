<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

/**
 * A map heatmap — a metric bucketed into geohash cells of a geo_point field ("orders by area",
 * "errors by region"). Higher $precision means smaller, more granular cells. Backed by the
 * `geohash_grid` aggregation with a metric sub-aggregation per cell.
 */
class Geo extends Widget
{
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected string $field,
        protected int $precision,
        protected ?int $size,
        protected Metric $metric,
        protected string $metricField,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $aggs->geoHashGrid('grid', $this->field, $this->precision, $this->size)
                ->aggregate(fn (Aggs $m) => $this->metric->apply($m, 'metric', $this->metricField));
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $buckets = new Collection($aggregations[$this->name]['grid']['buckets'] ?? []);

        return [
            'type' => 'geo',
            'metric' => $this->metric->value,
            'field' => $this->field,
            'precision' => $this->precision,
            'buckets' => $buckets->map(fn (array $bucket): array => [
                'geohash' => $bucket['key'],
                'value' => $this->metric->extract($bucket['metric'] ?? []),
                'count' => $bucket['doc_count'] ?? 0,
            ])->toArray(),
        ];
    }
}
