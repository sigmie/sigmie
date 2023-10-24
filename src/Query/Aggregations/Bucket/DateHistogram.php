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
        protected CalendarInterval $interval
    ) {
    }

    public function value(): array
    {
        $value = [
            'date_histogram' => [
                'field' => $this->field,
                'calendar_interval' => $this->interval->value,
            ],
        ];

        if (isset($this->missing)) {
            $value['date_histogram']['missing'] = $this->missing;
        }

        return $value;
    }
}
