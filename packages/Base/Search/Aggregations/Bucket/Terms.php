<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Bucket;

use Sigmie\Base\Contracts\Aggregation;
use Sigmie\Base\Search\Aggregations\Enums\CalendarInterval;
use Sigmie\Base\Shared\Missing;

class Terms extends Bucket
{
    use Missing;

    public function __construct(
        protected string $name,
        protected string $field
    ) {
    }

    public function value(): array
    {
        $value = [
            "terms" => [
                "field" => $this->field,
            ]
        ];

        if (isset($this->missing)) {
            $value['terms']['missing'] = $this->missing;
        }

        return $value;
    }
}
