<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Mappings\Types\Type;
use Sigmie\Query\Aggregations\Bucket\Terms as TermsAggregation;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Term\Terms;
use Sigmie\Shared\Collection;

use function Sigmie\Functions\object_map;

/**
 * A grouped ranking where each group carries several metrics, such as count plus average rating.
 */
class GroupedMetrics extends Widget
{
    /** @var array<string, array{label: string, values: list<string|bool>}> */
    protected array $aliases;

    /**
     * @param  list<array{key: string, label: string, metric: Metric, field: string, counted?: string}>  $metrics
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
        protected ?int $minCount = null,
        protected array $bucketAliases = [],
        protected bool $aliasesOnly = false,
        protected ?Type $groupType = null,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
        $this->aliases = $this->normalizeBucketAliases($this->bucketAliases, $groupType);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            if (! $this->aliasesOnly) {
                $terms = $aggs->terms('groups', $this->groupBy)
                    ->size(max($this->limit, 100));
                $this->rankGroups($terms);

                if ($this->minCount !== null) {
                    $terms->minDocCount($this->minCount);
                }

                $terms->aggregate(fn (Aggs $sub) => $this->addMetrics($sub, withLimit: true));

                $excluded = $this->excludedAliasValues($this->aliases, $this->groupType);

                if ($excluded !== []) {
                    $terms->exclude($excluded);
                }
            }

            foreach ($this->aliases as $key => $alias) {
                $filter = $aggs->filter($key, new Terms($this->groupBy, $alias['values']));

                if ($this->hasAggregatedMetrics()) {
                    $filter->aggregate(fn (Aggs $sub) => $this->addMetrics($sub));
                }
            }
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $rows = [
            ...(new Collection($aggregations[$this->name]['groups']['buckets'] ?? []))
                ->map(fn (array $bucket): array => $this->row(self::bucketLabel($bucket['key_as_string'] ?? $bucket['key']), $bucket))
                ->toArray(),
            ...$this->aliasRows($aggregations),
        ];

        usort($rows, fn (array $a, array $b): int => self::compareRankedRows($a, $b, $this->direction));

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
                'field' => $this->reportedField($metric),
            ], $this->metrics),
            'min_count' => $this->minCount ?? 1,
            'sort_metric' => $this->sortMetric,
            ...($this->bucketAliases !== [] ? ['bucket_aliases' => object_map($this->bucketAliases)] : []),
            ...($this->aliasesOnly ? ['bucket_aliases_only' => true] : []),
        ];
    }

    protected function addMetrics(Aggs $aggs, bool $withLimit = false): void
    {
        foreach ($this->metrics as $metric) {
            if ($this->usesBucketCount($metric)) {
                continue;
            }

            $metric['metric']->apply($aggs, $this->metricAggName($metric['key']), $metric['field'], $metric['counted'] ?? $this->dateField);
            if ($metric['metric'] !== Metric::Count) {
                $aggs->valueCount($this->populationAggName($metric['key']), $metric['field']);
            }
        }

        if ($withLimit) {
            // The terms are already ranked. Sorting again drops buckets with a null metric.
            $aggs->sort($this->pipelineAggName('limit'), [], $this->limit);
        }
    }

    protected function hasAggregatedMetrics(): bool
    {
        foreach ($this->metrics as $metric) {
            if (! $this->usesBucketCount($metric)) {
                return true;
            }
        }

        return false;
    }

    /** @param array{field: string, counted?: string} $metric */
    private function reportedField(array $metric): string
    {
        return $metric['counted'] ?? $metric['field'];
    }

    /** @param array{metric: Metric, field: string, counted?: string} $metric */
    private function usesBucketCount(array $metric): bool
    {
        return $metric['metric'] === Metric::Count && $metric['field'] === '' && ! isset($metric['counted']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(string $key, array $bucket, array $sourceKeys = []): array
    {
        $documentCount = (int) ($bucket['doc_count'] ?? 0);
        $metrics = (new Collection($this->metrics))
            ->mapWithKeys(fn (array $metric): array => [
                $metric['key'] => $this->usesBucketCount($metric)
                    ? $documentCount
                    : $metric['metric']->extract($bucket[$this->metricAggName($metric['key'])] ?? []),
            ])
            ->toArray();
        $populations = (new Collection($this->metrics))
            ->mapWithKeys(fn (array $metric): array => [
                $metric['key'] => [
                    'document_count' => $documentCount,
                    'value_count' => $metric['metric'] === Metric::Count
                        ? (int) $metrics[$metric['key']]
                        : (int) ($bucket[$this->populationAggName($metric['key'])]['value'] ?? 0),
                    'field' => $this->reportedField($metric),
                ],
            ])
            ->toArray();

        return [
            'key' => $key,
            'value' => array_key_exists($this->sortMetric, $metrics)
                ? $metrics[$this->sortMetric]
                : (reset($metrics) ?: 0),
            'count' => $documentCount,
            'metrics' => object_map($metrics),
            'metric_populations' => object_map($populations),
            ...($sourceKeys !== [] ? ['source_keys' => $sourceKeys] : []),
        ];
    }

    /** @return list<array<string, mixed>> */
    protected function aliasRows(array $aggregations): array
    {
        return (new Collection(array_intersect_key($this->aliases, $aggregations[$this->name] ?? [])))
            ->map(function (array $alias, string $key) use ($aggregations): array {
                $bucket = $aggregations[$this->name][$key];

                return $this->row($alias['label'], $bucket, $alias['values']);
            })
            ->filter(fn (array $row): bool => $row['count'] >= ($this->minCount ?? 1))
            ->values();
    }

    protected function populationAggName(string $metricKey): string
    {
        // A metric key may equal this prefix. The suffix keeps the value_count off that key.
        $taken = array_column($this->metrics, 'key');
        $name = 'population_'.substr(md5($metricKey), 0, 12);

        while (in_array($name, $taken, true)) {
            $name .= '_population';
        }

        return $name;
    }

    protected function pipelineAggName(string $name): string
    {
        $keys = array_column($this->metrics, 'key');

        if (! in_array($name, $keys, true)) {
            return $name;
        }

        $alias = 'pipeline_'.substr(md5($name), 0, 12);

        while (in_array($alias, $keys, true)) {
            $alias .= '_pipeline';
        }

        return $alias;
    }

    protected function metricAggName(string $key): string
    {
        if (! in_array($key, ['_key', '_count', 'key', 'key_as_string', 'doc_count'], true) && strpbrk($key, '.>[]') === false) {
            return $key;
        }

        $taken = array_column($this->metrics, 'key');
        $name = 'metric_'.bin2hex($key);
        while (in_array($name, $taken, true)) {
            $name .= '_metric';
        }

        return $name;
    }

    protected function rankGroups(TermsAggregation $terms): void
    {
        $metric = null;

        foreach ($this->metrics as $candidate) {
            if ($candidate['key'] === $this->sortMetric) {
                $metric = $candidate;

                break;
            }
        }

        if (! is_array($metric) || $this->usesBucketCount($metric)) {
            $terms->order('_count', $this->direction);

            return;
        }

        $name = $this->metricAggName($metric['key']);

        $metric['metric']->rank($terms, $name, $metric['field'], $this->direction, $this->pipelineAggName('metric_present'));
    }
}
