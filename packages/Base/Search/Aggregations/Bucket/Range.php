<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Bucket;

use Sigmie\Base\Contracts\Aggregation;
use Sigmie\Base\Search\Aggregations\Enums\CalendarInterval;
use Sigmie\Base\Shared\Missing;

class Range extends Bucket
{
    public function __construct(
        protected string $name,
        protected string $field,
        protected array $ranges
    ) {
    }

    public function value(): array
    {
        $value = [
            "range" => [
                "field" => $this->field,
                "ranges"=> $this->ranges
            ]
        ];

        return $value;
    }
}
