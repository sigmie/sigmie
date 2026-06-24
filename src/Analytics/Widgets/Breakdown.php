<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Term\Terms;
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
        protected array $bucketAliases = [],
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $terms = $aggs->terms('groups', $this->groupBy)
                ->size($this->limit)
                ->order($this->metric->orderKey('metric'), $this->direction)
                ->aggregate(fn (Aggs $sub) => $this->metric->apply($sub, 'metric', $this->field));

            $excluded = $this->excludedAliasValues();

            if ($excluded !== []) {
                $terms->exclude($excluded);
            }

            foreach ($this->normalizedAliases() as $key => $alias) {
                $aggs->filter($key, new Terms($this->groupBy, $alias['values']))
                    ->aggregate(fn (Aggs $sub) => $this->metric->apply($sub, 'metric', $this->field));
            }
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $groups = new Collection($aggregations[$this->name]['groups']['buckets'] ?? []);

        $rows = [
            ...$groups->map(fn (array $bucket): array => [
                'key' => $bucket['key'],
                'value' => $this->metric->extract($bucket['metric'] ?? []),
                'count' => $bucket['doc_count'] ?? 0,
            ])->toArray(),
            ...$this->aliasRows($aggregations),
        ];

        usort($rows, fn (array $a, array $b): int => $this->compareRows($a, $b));

        return [
            'type' => 'breakdown',
            'metric' => $this->metric->value,
            'field' => $this->field,
            'group_by' => $this->groupBy,
            'rows' => array_slice($rows, 0, $this->limit),
            ...($this->bucketAliases !== [] ? ['bucket_aliases' => $this->bucketAliases] : []),
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
            $values = array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                (array) $values,
            ), static fn (string $value): bool => $value !== ''));
            if ($label === '') {
                continue;
            }

            if ($values === []) {
                continue;
            }

            $aliases[$this->aliasAggName($label)] = [
                'label' => $label,
                'values' => array_values(array_unique($values)),
            ];
        }

        return $aliases;
    }

    /**
     * @return list<string>
     */
    protected function excludedAliasValues(): array
    {
        return (new Collection($this->normalizedAliases()))
            ->flatMap(fn (array $alias): array => $alias['values'])
            ->unique()
            ->values();
    }

    /**
     * @return list<array{key: string, value: int|float|null, count: int, source_keys: list<string>}>
     */
    protected function aliasRows(array $aggregations): array
    {
        return (new Collection($this->normalizedAliases()))
            ->map(function (array $alias, string $key) use ($aggregations): array {
                $bucket = $aggregations[$this->name][$key] ?? [];

                return [
                    'key' => $alias['label'],
                    'value' => $this->metric->extract($bucket['metric'] ?? []),
                    'count' => $bucket['doc_count'] ?? 0,
                    'source_keys' => $alias['values'],
                ];
            })
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->values();
    }

    protected function compareRows(array $a, array $b): int
    {
        $left = (float) ($a['value'] ?? 0);
        $right = (float) ($b['value'] ?? 0);

        // @codeCoverageIgnoreStart
        if ($left === $right) {
            return ((string) $a['key']) <=> ((string) $b['key']);
        }
        // @codeCoverageIgnoreEnd

        return $this->direction === 'asc'
            ? $left <=> $right
            : $right <=> $left;
    }

    protected function aliasAggName(string $label): string
    {
        return 'alias_'.substr(md5($label), 0, 12);
    }
}
