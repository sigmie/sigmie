<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class Histogram extends Bucket
{
    public function __construct(
        protected string $name,
        protected string $field,
        protected int|float $interval,
        protected int $minDocCount = 0,
        protected ?array $extendedBounds = null
    ) {}

    protected function value(): array
    {
        $value = [
            'histogram' => [
                'field' => $this->field,
                'interval' => $this->interval,
                'min_doc_count' => $this->minDocCount,
            ],
        ];

        if ($this->extendedBounds) {
            $value['histogram']['extended_bounds'] = $this->extendedBounds;
        }

        return $value;
    }
}
