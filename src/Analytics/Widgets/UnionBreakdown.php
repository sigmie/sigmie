<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

class UnionBreakdown extends Widget
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
            $terms = $aggs->unionTerms('groups', $this->groupBy)
                ->size($this->limit)
                ->aggregate(fn (Aggs $sub) => $this->addMetric($sub));
            $this->metric->rank($terms, 'metric', $this->field, $this->direction);
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $groups = new Collection($aggregations[$this->name]['groups']['buckets'] ?? []);

        return [
            'type' => 'union_breakdown',
            'metric' => $this->metric->value,
            'field' => $this->metric->resultField($this->field, $this->countedField()),
            'group_by_fields' => $this->groupBy,
            'rows' => $groups->map(fn (array $bucket): array => [
                'key' => $bucket['key'],
                'value' => $this->metric->extract($bucket['metric'] ?? []),
                'count' => $bucket['doc_count'] ?? 0,
                'population' => $this->metric->population($bucket, $this->metric->resultField($this->field, $this->countedField())),
            ])->toArray(),
        ];
    }

    protected function addMetric(Aggs $aggs): void
    {
        $this->metric->apply($aggs, 'metric', $this->field, $this->countedField());

        if ($this->metric !== Metric::Count) {
            $aggs->valueCount('metric_population', $this->field);
        }
    }
}
