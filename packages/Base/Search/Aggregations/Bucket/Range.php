<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Bucket;

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
        return [
            'range' => [
                'field' => $this->metricField,
                'ranges'=> $this->ranges,
            ],
        ];
    }
}
