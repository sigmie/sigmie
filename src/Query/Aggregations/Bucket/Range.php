<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class Range extends Bucket
{
    public function __construct(
        protected string $name,
        protected string $field,
        protected array $ranges
    ) {}

    protected function value(): array
    {
        return [
            'range' => [
                'field' => $this->field,
                'ranges' => array_map(
                    static fn (mixed $range): mixed => $range === [] ? (object) [] : $range,
                    array_values($this->ranges),
                ),
            ],
        ];
    }
}
