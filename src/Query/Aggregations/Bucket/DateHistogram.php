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
        protected CalendarInterval $interval,
        protected int $minDocCount = 0,
        protected null|array $extendedBounds = null
    ) {
    }

    public function value(): array
    {
        $value = [
            'date_histogram' => [
                'field' => $this->field,
                'calendar_interval' => $this->interval->value,
                'min_doc_count' => $this->minDocCount,
            ],
        ];

        if (isset($this->missing)) {
            $value['date_histogram']['missing'] = $this->missing;
        }

        if (!is_null($this->extendedBounds)) {
            $value['date_histogram']['extended_bounds'] = $this->extendedBounds;
        }

        return $value;
    }
}
