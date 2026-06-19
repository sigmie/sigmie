<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

/**
 * A numeric histogram where each bucket carries a metric, such as average rating by calorie bucket.
 */
class HistogramMetric extends Widget
{
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected string $bucketField,
        protected int $interval,
        protected Metric $metric,
        protected string $field,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $aggs->histogram('buckets', $this->bucketField, $this->interval)
                ->aggregate(fn (Aggs $sub) => $this->metric->apply($sub, 'metric', $this->field));
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $buckets = (new Collection($aggregations[$this->name]['buckets']['buckets'] ?? []))
            ->map(fn (array $bucket): array => [
                'label' => $bucket['key'],
                'value' => $this->metric === Metric::Count
                    ? ($bucket['doc_count'] ?? 0)
                    : $this->metric->extract($bucket['metric'] ?? []),
                'count' => $bucket['doc_count'] ?? 0,
            ])
            ->filter(fn (array $bucket): bool => (int) ($bucket['count'] ?? 0) > 0 && ($bucket['value'] ?? null) !== null)
            ->values();

        return [
            'type' => 'histogram_metric',
            'metric' => $this->metric->value,
            'field' => $this->field,
            'bucket_field' => $this->bucketField,
            'interval' => $this->interval,
            'series' => array_map(fn (array $bucket): array => [
                'label' => $bucket['label'],
                'value' => $bucket['value'],
                'count' => $bucket['count'],
            ], $buckets),
            'rows' => array_map(fn (array $bucket): array => [
                'key' => $bucket['label'],
                'value' => $bucket['value'],
                'count' => $bucket['count'],
            ], $buckets),
        ];
    }
}
