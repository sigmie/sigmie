<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

use function Sigmie\Functions\object_map;

class MultiBreakdown extends Widget
{
    /**
     * Names the caller passed. The aggregation fields may be `.keyword` subfields.
     *
     * @var list<string>
     */
    protected array $keyNames;

    /**
     * @param  list<string>  $groupBy
     * @param  list<string>|null  $keyNames
     */
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected array $groupBy,
        protected Metric $metric,
        protected string $field,
        protected int $limit,
        protected string $direction,
        ?array $keyNames = null,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);

        $this->keyNames = $keyNames ?? $groupBy;
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $terms = $aggs->multiTerms('groups', $this->groupBy)
                ->size($this->limit)
                ->aggregate(fn (Aggs $sub) => $this->metric->apply($sub, 'metric', $this->field, $this->countedField()));
            $this->metric->rank($terms, 'metric', $this->field, $this->direction);
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $groups = new Collection($aggregations[$this->name]['groups']['buckets'] ?? []);

        return [
            'type' => 'multi_breakdown',
            'metric' => $this->metric->value,
            'field' => $this->metric->resultField($this->field, $this->countedField()),
            'group_by' => $this->groupBy,
            'rows' => $groups->map(fn (array $bucket): array => $this->row($bucket))->toArray(),
        ];
    }

    /**
     * @return array{key: array<string, mixed>, key_values: list<mixed>, label: string, value: int|float|null, count: int}
     */
    protected function row(array $bucket): array
    {
        $values = (array) ($bucket['key'] ?? []);

        return [
            'key' => object_map(array_combine($this->keyNames, $values) ?: []),
            'key_values' => array_values($values),
            'label' => implode(' / ', array_map(self::bucketLabel(...), $values)),
            'value' => $this->metric->extract($bucket['metric'] ?? []),
            'count' => $bucket['doc_count'] ?? 0,
        ];
    }
}
