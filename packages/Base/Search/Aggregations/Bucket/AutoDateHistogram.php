<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Bucket;

use Sigmie\Base\Search\Aggregations\Enums\MinimumInterval;
use Sigmie\Base\Shared\Missing;

class AutoDateHistogram extends Bucket
{
    use Missing;

    public function __construct(
        protected string $name,
        protected string $field,
        protected int $buckets,
        protected MinimumInterval $minimumInterval = MinimumInterval::Second
    ) {
    }

    public function value(): array
    {
        $value = [
            'auto_date_histogram' => [
                'field' => $this->field,
                'buckets' => $this->buckets,
                'minimum_interval' => $this->minimumInterval->value
            ],
        ];

        if (isset($this->missing)) {
            $value['date_histogram']['missing'] = $this->missing;
        }

        return $value;
    }
}
