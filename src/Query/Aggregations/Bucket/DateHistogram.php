<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Shared\Missing;

class DateHistogram extends Bucket
{
    use Missing;

    public function __construct(
        protected string $name,
        protected string $field,
        protected CalendarInterval|string $interval,
        protected int $minDocCount = 0,
        protected ?array $extendedBounds = null,
        protected ?string $format = null,
        protected ?string $timeZone = null,
    ) {}

    protected function value(): array
    {
        // A CalendarInterval (1d, 1w, 1M…) is calendar-aware; a raw string (15d, 90m, 12h) is a
        // fixed interval — Elasticsearch requires the latter for any multiple of a unit.
        [$key, $interval] = $this->interval instanceof CalendarInterval
            ? ['calendar_interval', $this->interval->value]
            : ['fixed_interval', $this->interval];

        $value = [
            'date_histogram' => [
                'field' => $this->field,
                $key => $interval,
                'min_doc_count' => $this->minDocCount,
            ],
        ];

        if (! is_null($this->timeZone)) {
            $value['date_histogram']['time_zone'] = $this->timeZone;
        }

        if (isset($this->missing)) {
            $value['date_histogram']['missing'] = $this->missing;
        }

        if (! is_null($this->extendedBounds)) {
            $value['date_histogram']['extended_bounds'] = $this->extendedBounds;
        }

        if (! is_null($this->format)) {
            $value['date_histogram']['format'] = $this->format;
        }

        return $value;
    }
}
