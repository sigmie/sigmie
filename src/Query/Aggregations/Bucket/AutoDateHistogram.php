<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Aggregations\Enums\MinimumInterval;
use Sigmie\Query\Shared\Missing;

class AutoDateHistogram extends Bucket
{
    use Missing;

    public function __construct(
        protected string $name,
        protected string $field,
        protected int $buckets,
        protected MinimumInterval $minimumInterval = MinimumInterval::Second,
        protected ?string $timeZone = null,
    ) {}

    protected function value(): array
    {
        $value = [
            'auto_date_histogram' => [
                'field' => $this->field,
                'buckets' => $this->buckets,
                'minimum_interval' => $this->minimumInterval->value,
            ],
        ];

        if (! is_null($this->timeZone)) {
            $value['auto_date_histogram']['time_zone'] = $this->timeZone;
        }

        if (isset($this->missing)) {
            $value['date_histogram']['missing'] = $this->missing;
        }

        return $value;
    }
}
