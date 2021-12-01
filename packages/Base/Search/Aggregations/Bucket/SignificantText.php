<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Bucket;

use Sigmie\Base\Contracts\Aggregation;
use Sigmie\Base\Search\Aggregations\Enums\CalendarInterval;
use Sigmie\Base\Shared\Missing;

class SignificantText extends Bucket
{
    public function __construct(
        protected string $name,
        protected string $field,
    ) {
    }

    public function value(): array
    {
        $value = [
            "significant_text" => [
                "field" => $this->field,
            ]
        ];

        return $value;
    }
}
