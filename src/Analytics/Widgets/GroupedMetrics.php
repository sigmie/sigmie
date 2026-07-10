<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Term\Terms;
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
        protected array $bucketAliases = [],
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $terms = $aggs->terms('groups', $this->groupBy)
                ->size(max($this->limit, 100))
                ->order($this->sortOrderKey(), $this->direction);

            $terms->aggregate(fn (Aggs $sub) => $this->addMetrics($sub, withLimit: true));

            $excluded = $this->excludedAliasValues();

            if ($excluded !== []) {
                $terms->exclude($excluded);
            }

            foreach ($this->normalizedAliases() as $key => $alias) {
                $aggs->filter($key, new Terms($this->groupBy, $alias['values']))
                    ->aggregate(fn (Aggs $sub) => $this->addMetrics($sub));
            }
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $rows = [
            ...(new Collection($aggregations[$this->name]['groups']['buckets'] ?? []))
                ->map(fn (array $bucket): array => $this->row((string) $bucket['key'], $bucket))
                ->toArray(),
            ...$this->aliasRows($aggregations),
        ];

        usort($rows, fn (array $a, array $b): int => $this->compareRows($a, $b));

        return [
            'type' => 'grouped_metrics',
            'metric' => $this->sortMetric,
            'field' => $this->sortMetric,
            'group_by' => $this->groupBy,
            'rows' => array_slice($rows, 0, $this->limit),
            'metrics' => array_map(fn (array $metric): array => [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'metric' => $metric['metric']->value,
                'field' => $metric['field'],
            ], $this->metrics),
            'min_count' => $this->minCount,
            'sort_metric' => $this->sortMetric,
            ...($this->bucketAliases !== [] ? ['bucket_aliases' => $this->bucketAliases] : []),
        ];
    }

    protected function addMetrics(Aggs $aggs, bool $withLimit = false): void
    {
        foreach ($this->metrics as $metric) {
            if ($metric['metric'] === Metric::Count) {
                continue;
            }

            $metric['metric']->apply($aggs, $metric['key'], $metric['field']);
            $aggs->valueCount($this->populationAggName($metric['key']), $metric['field']);
        }

        if ($this->minCount > 0 && $withLimit) {
            $aggs->bucketSelector('min_count', ['count' => '_count'], 'params.count >= '.$this->minCount);
        }

        if ($withLimit) {
            $aggs->sort('limit', [[$this->sortOrderKey() => ['order' => $this->direction]]], $this->limit);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(string $key, array $bucket, array $sourceKeys = []): array
    {
        $documentCount = (int) ($bucket['doc_count'] ?? 0);
        $metrics = (new Collection($this->metrics))
            ->mapWithKeys(fn (array $metric): array => [
                $metric['key'] => $metric['metric'] === Metric::Count
                    ? $documentCount
                    : $metric['metric']->extract($bucket[$metric['key']] ?? []),
            ])
            ->toArray();
        $populations = (new Collection($this->metrics))
            ->mapWithKeys(fn (array $metric): array => [
                $metric['key'] => [
                    'document_count' => $documentCount,
                    'value_count' => $metric['metric'] === Metric::Count
                        ? $documentCount
                        : (int) ($bucket[$this->populationAggName($metric['key'])]['value'] ?? 0),
                    'field' => $metric['field'],
                ],
            ])
            ->toArray();

        return [
            'key' => $key,
            'value' => $metrics[$this->sortMetric] ?? reset($metrics) ?: 0,
            'count' => $documentCount,
            'metrics' => $metrics,
            'metric_populations' => $populations,
            ...($sourceKeys !== [] ? ['source_keys' => $sourceKeys] : []),
        ];
    }

    /**
     * @return array<string, array{label: string, values: list<string>}>
     */
    protected function normalizedAliases(): array
    {
        $aliases = [];

        foreach ($this->bucketAliases as $label => $values) {
            $label = trim((string) $label);
            $values = array_values(array_unique(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                [$label, ...(array) $values],
            ), static fn (string $value): bool => $value !== '')));
            if ($label === '') {
                continue;
            }
            if ($values === []) {
                continue;
            }

            $aliases[$this->aliasAggName($label)] = ['label' => $label, 'values' => $values];
        }

        return $aliases;
    }

    /** @return list<string> */
    protected function excludedAliasValues(): array
    {
        return (new Collection($this->normalizedAliases()))
            ->flatMap(fn (array $alias): array => $alias['values'])
            ->unique()
            ->values();
    }

    /** @return list<array<string, mixed>> */
    protected function aliasRows(array $aggregations): array
    {
        return (new Collection($this->normalizedAliases()))
            ->map(function (array $alias, string $key) use ($aggregations): array {
                $bucket = $aggregations[$this->name][$key] ?? [];

                return $this->row($alias['label'], $bucket, $alias['values']);
            })
            ->filter(fn (array $row): bool => $row['count'] >= max(1, $this->minCount))
            ->values();
    }

    protected function compareRows(array $a, array $b): int
    {
        $left = (float) ($a['value'] ?? 0);
        $right = (float) ($b['value'] ?? 0);

        if ($left === $right) {
            return ((string) $a['key']) <=> ((string) $b['key']);
        }

        return $this->direction === 'asc' ? $left <=> $right : $right <=> $left;
    }

    protected function aliasAggName(string $label): string
    {
        return 'alias_'.substr(md5($label), 0, 12);
    }

    protected function populationAggName(string $metricKey): string
    {
        return 'population_'.substr(md5($metricKey), 0, 12);
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
