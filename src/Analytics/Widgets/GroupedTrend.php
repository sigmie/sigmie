<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

/**
 * A trend split into one series per value of a dimension — the stacked/grouped chart
 * ("errors by level over time", "revenue by product over time").
 */
class GroupedTrend extends Widget
{
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected Metric $metric,
        protected string $field,
        protected string $groupBy,
        protected CalendarInterval|string $interval,
        protected int $limit,
        protected ?string $timeZone = null,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $aggs->terms('groups', $this->groupBy)
                ->size($this->limit)
                ->aggregate(function (Aggs $sub): void {
                    $sub->dateHistogram('series', $this->dateField, $this->interval, extendedBounds: $this->extendedBounds(), timeZone: $this->timeZone)
                        ->aggregate(fn (Aggs $m) => $this->metric->apply($m, 'metric', $this->field));
                });
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $groups = new Collection($aggregations[$this->name]['groups']['buckets'] ?? []);

        return [
            'type' => 'grouped_trend',
            'metric' => $this->metric->value,
            'field' => $this->field,
            'group_by' => $this->groupBy,
            'interval' => $this->interval instanceof CalendarInterval ? $this->interval->name : $this->interval,
            'groups' => $groups->map(fn (array $group): array => [
                'group' => $group['key'],
                'series' => array_map(fn (array $bucket): array => [
                    'label' => $bucket['key_as_string'],
                    'value' => $this->metric->extract($bucket['metric'] ?? []),
                ], $group['series']['buckets'] ?? []),
            ])->toArray(),
        ];
    }
}
