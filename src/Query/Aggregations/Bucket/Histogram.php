<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class Histogram extends Bucket
{
    public function __construct(
        protected string $name,
        protected string $field,
        protected int $interval,
        protected int $minDocCount = 0
    ) {
    }

    public function value(): array
    {
        $value = [
            'histogram' => [
                'field' => $this->field,
                'interval' => $this->interval,
                'min_doc_count' => $this->minDocCount,
            ],
        ];

        return $value;
    }
}
