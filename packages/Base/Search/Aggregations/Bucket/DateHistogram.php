<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Bucket;

use Sigmie\Base\Contracts\Aggregation;
use Sigmie\Base\Shared\Missing;
use Sigmie\Base\Search\Aggregations\Enums\CalendarInterval;

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
            "date_histogram" => [
                "field" => $this->field,
                "calendar_interval" => $this->interval->value,
            ]
        ];

        if (isset($this->missing)) {
            $value['date_histogram']['missing'] = $this->missing;
        }

        return $value;
    }
}
