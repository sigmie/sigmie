<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

/**
 * A cohort retention grid — entities grouped by the period of their cohort date (e.g. sign-up),
 * then counted, distinct, across each later activity period. Reads as the classic triangular
 * retention table: one row per cohort, one cell per period.
 *
 * Backed by a `date_histogram` on the cohort field, a nested `date_histogram` on the activity
 * field, and a `cardinality` of the entity id so each cell counts distinct entities, not events.
 */
class Retention extends Widget
{
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected string $cohortField,
        protected string $idField,
        protected CalendarInterval|string $interval,
        protected ?string $timeZone = null,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $aggs->dateHistogram('cohorts', $this->cohortField, $this->interval, timeZone: $this->timeZone)
                ->aggregate(function (Aggs $sub): void {
                    $sub->cardinality('size', $this->idField);
                    $sub->dateHistogram('periods', $this->dateField, $this->interval, extendedBounds: $this->extendedBounds(), timeZone: $this->timeZone)
                        ->aggregate(fn (Aggs $m) => $m->cardinality('users', $this->idField));
                });
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $cohorts = new Collection($aggregations[$this->name]['cohorts']['buckets'] ?? []);

        return [
            'type' => 'retention',
            'cohort_field' => $this->cohortField,
            'interval' => $this->interval instanceof CalendarInterval ? $this->interval->name : $this->interval,
            'cohorts' => $cohorts->map(fn (array $cohort): array => [
                'cohort' => $cohort['key_as_string'],
                'size' => $cohort['size']['value'] ?? 0,
                'periods' => array_map(fn (array $bucket): array => [
                    'label' => $bucket['key_as_string'],
                    'value' => $bucket['users']['value'] ?? 0,
                ], $cohort['periods']['buckets'] ?? []),
            ])->toArray(),
        ];
    }
}
