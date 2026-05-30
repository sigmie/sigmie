<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

/**
 * A histogram of a numeric field — the distribution chart ("order-size spread",
 * "response-time buckets"). Buckets are fixed-width by $interval.
 */
class Distribution extends Widget
{
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected string $field,
        protected int $interval,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $aggs->histogram('buckets', $this->field, $this->interval);
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $buckets = new Collection($aggregations[$this->name]['buckets']['buckets'] ?? []);

        return [
            'type' => 'distribution',
            'field' => $this->field,
            'interval' => $this->interval,
            'buckets' => $buckets->map(fn (array $bucket): array => [
                'label' => $bucket['key'],
                'count' => $bucket['doc_count'] ?? 0,
            ])->toArray(),
        ];
    }
}
