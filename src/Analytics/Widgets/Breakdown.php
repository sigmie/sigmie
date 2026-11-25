<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Mappings\Types\Type;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Term\Terms;
use Sigmie\Shared\Collection;

use function Sigmie\Functions\object_map;

/**
 * A top-N ranked list — a dimension ranked by a metric ("top products by revenue",
 * "top issues by event count").
 */
class Breakdown extends Widget
{
    /** @var array<string, array{label: string, values: list<string|bool>}> */
    protected array $aliases;

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
        protected ?Type $groupType = null,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
        $this->aliases = $this->normalizeBucketAliases($this->bucketAliases, $groupType);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $terms = $aggs->terms('groups', $this->groupBy)
                ->size($this->limit)
                ->aggregate(fn (Aggs $sub) => $this->addMetric($sub));
            $this->metric->rank($terms, 'metric', $this->field, $this->direction);

            $terms->exclude($this->excludedAliasValues($this->aliases, $this->groupType));

            foreach ($this->aliases as $key => $alias) {
                $aggs->filter($key, new Terms($this->groupBy, $alias['values']))
                    ->aggregate(fn (Aggs $sub) => $this->addMetric($sub));
            }
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $groups = new Collection($aggregations[$this->name]['groups']['buckets'] ?? []);

        $rows = [
            ...$groups->map(fn (array $bucket): array => [
                'key' => self::termsKey($bucket),
                'value' => $this->metric->extract($bucket['metric'] ?? []),
                'count' => $bucket['doc_count'] ?? 0,
                'population' => $this->metric->population($bucket, $this->metric->resultField($this->field, $this->countedField())),
            ])->toArray(),
            ...$this->aliasRows($aggregations),
        ];

        usort($rows, fn (array $a, array $b): int => self::compareRankedRows($a, $b, $this->direction));

        return [
            'type' => 'breakdown',
            'metric' => $this->metric->value,
            'field' => $this->metric->resultField($this->field, $this->countedField()),
            'group_by' => $this->groupBy,
            'rows' => array_slice($rows, 0, $this->limit),
            ...($this->bucketAliases !== [] ? ['bucket_aliases' => object_map($this->bucketAliases)] : []),
        ];
    }

    protected function addMetric(Aggs $aggs): void
    {
        $this->metric->apply($aggs, 'metric', $this->field, $this->countedField());

        if ($this->metric !== Metric::Count) {
            $aggs->valueCount('metric_population', $this->field);
        }
    }

    /**
     * @return list<array{key: string, value: int|float|null, count: int, source_keys: list<string|bool>}>
     */
    protected function aliasRows(array $aggregations): array
    {
        return (new Collection($this->aliases))
            ->map(function (array $alias, string $key) use ($aggregations): array {
                $bucket = $aggregations[$this->name][$key] ?? [];

                return [
                    'key' => $alias['label'],
                    'value' => $this->metric->extract($bucket['metric'] ?? []),
                    'count' => $bucket['doc_count'] ?? 0,
                    'population' => $this->metric->population($bucket, $this->metric->resultField($this->field, $this->countedField())),
                    'source_keys' => $alias['values'],
                ];
            })
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->values();
    }
}
