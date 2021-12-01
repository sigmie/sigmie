<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Bucket;

use Sigmie\Base\Contracts\Aggregation;
use Sigmie\Base\Search\Aggregations\Enums\CalendarInterval;

class Missing extends Bucket
{
    public function __construct(
        protected string $name,
        protected string $field
    ) {
    }

    public function value(): array
    {
        $value = [
            "missing" => [
                "field" => $this->field,
            ]
        ];

        return $value;
    }
}
