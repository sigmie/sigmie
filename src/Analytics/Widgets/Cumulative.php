<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

/**
 * A running total over time — the cumulative growth curve ("total customers", "MRR to date").
 * A date histogram whose per-bucket metric is fed through a `cumulative_sum` pipeline.
 */
class Cumulative extends Widget
{
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected Metric $metric,
        protected string $field,
        protected CalendarInterval|string $interval,
        protected ?string $timeZone = null,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $aggs->dateHistogram('series', $this->dateField, $this->interval, extendedBounds: $this->extendedBounds(), timeZone: $this->timeZone)
                ->aggregate(function (Aggs $sub): void {
                    $this->metric->apply($sub, 'metric', $this->field);
                    $sub->cumulativeSum('cumulative', 'metric');
                });
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $buckets = new Collection($aggregations[$this->name]['series']['buckets'] ?? []);

        return [
            'type' => 'cumulative',
            'metric' => $this->metric->value,
            'field' => $this->field,
            'interval' => $this->interval instanceof CalendarInterval ? $this->interval->name : $this->interval,
            'series' => $buckets->map(fn (array $bucket): array => [
                'label' => $bucket['key_as_string'],
                'value' => $bucket['cumulative']['value'] ?? null,
            ])->toArray(),
        ];
    }
}
