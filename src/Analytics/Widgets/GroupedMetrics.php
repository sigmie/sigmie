<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

/**
 * A grouped ranking where each group carries several metrics, such as count plus average rating.
 */
class GroupedMetrics extends Widget
{
    /**
     * @param  list<array{key: string, label: string, metric: Metric, field: string}>  $metrics
     */
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected string $groupBy,
        protected array $metrics,
        protected string $sortMetric,
        protected int $limit,
        protected string $direction,
        protected int $minCount = 0,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $terms = $aggs->terms('groups', $this->groupBy)
                ->size(max($this->limit, 100))
                ->order($this->sortOrderKey(), $this->direction);

            $terms->aggregate(function (Aggs $sub): void {
                foreach ($this->metrics as $metric) {
                    if ($metric['metric'] === Metric::Count) {
                        continue;
                    }

                    $metric['metric']->apply($sub, $metric['key'], $metric['field']);
                }

                if ($this->minCount > 0) {
                    $sub->bucketSelector('min_count', ['count' => '_count'], 'params.count >= '.$this->minCount);
                }

                $sub->sort('limit', [[$this->sortOrderKey() => ['order' => $this->direction]]], $this->limit);
            });
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $rows = (new Collection($aggregations[$this->name]['groups']['buckets'] ?? []))
            ->map(function (array $bucket): array {
                $metrics = (new Collection($this->metrics))
                    ->mapWithKeys(fn (array $metric): array => [
                        $metric['key'] => $metric['metric'] === Metric::Count
                            ? ($bucket['doc_count'] ?? 0)
                            : $metric['metric']->extract($bucket[$metric['key']] ?? []),
                    ])
                    ->toArray();

                return [
                    'key' => $bucket['key'],
                    'value' => $metrics[$this->sortMetric] ?? reset($metrics) ?: 0,
                    'count' => $bucket['doc_count'] ?? 0,
                    'metrics' => $metrics,
                ];
            })
            ->toArray();

        return [
            'type' => 'grouped_metrics',
            'metric' => $this->sortMetric,
            'field' => $this->sortMetric,
            'group_by' => $this->groupBy,
            'rows' => $rows,
            'metrics' => array_map(fn (array $metric): array => [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'metric' => $metric['metric']->value,
                'field' => $metric['field'],
            ], $this->metrics),
            'min_count' => $this->minCount,
            'sort_metric' => $this->sortMetric,
        ];
    }

    protected function sortOrderKey(): string
    {
        if ($this->sortMetric === 'count') {
            return '_count';
        }

        $metric = null;

        foreach ($this->metrics as $candidate) {
            if ($candidate['key'] === $this->sortMetric) {
                $metric = $candidate;

                break;
            }
        }

        if (! is_array($metric)) {
            return '_count';
        }

        if ($metric['metric'] === Metric::Count) {
            return '_count';
        }

        return $metric['metric']->orderKey($metric['key']);
    }
}
