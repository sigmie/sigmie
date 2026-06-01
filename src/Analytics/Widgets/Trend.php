<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

/**
 * A metric over time — the line/area/bar chart. Re-bucketing "per day → per month" is just a
 * different {@see CalendarInterval}; the rest of the widget is unchanged.
 */
class Trend extends Widget
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
            $aggs->dateHistogram('series', $this->dateField, $this->interval, timeZone: $this->timeZone)
                ->aggregate(fn (Aggs $sub) => $this->metric->apply($sub, 'metric', $this->field));
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $buckets = new Collection($aggregations[$this->name]['series']['buckets'] ?? []);

        return [
            'type' => 'trend',
            'metric' => $this->metric->value,
            'field' => $this->field,
            'interval' => $this->interval instanceof CalendarInterval ? $this->interval->name : $this->interval,
            'series' => $buckets->map(fn (array $bucket): array => [
                'label' => $bucket['key_as_string'],
                'value' => $this->metric->extract($bucket['metric'] ?? []),
            ])->toArray(),
        ];
    }
}
