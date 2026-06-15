<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

class MultiBreakdown extends Widget
{
    /**
     * @param  list<string>  $groupBy
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
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $aggs->multiTerms('groups', $this->groupBy)
                ->size($this->limit)
                ->order($this->metric->orderKey('metric'), $this->direction)
                ->aggregate(fn (Aggs $sub) => $this->metric->apply($sub, 'metric', $this->field));
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $groups = new Collection($aggregations[$this->name]['groups']['buckets'] ?? []);

        return [
            'type' => 'multi_breakdown',
            'metric' => $this->metric->value,
            'field' => $this->field,
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
            'key' => array_combine($this->groupBy, $values) ?: [],
            'key_values' => array_values($values),
            'label' => (string) ($bucket['key_as_string'] ?? implode(' / ', array_map(
                static fn (mixed $value): string => (string) $value,
                $values,
            ))),
            'value' => $this->metric->extract($bucket['metric'] ?? []),
            'count' => $bucket['doc_count'] ?? 0,
        ];
    }
}
