<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

class Rate extends Metric
{
    public function __construct(
        protected string $name,
        protected string $field,
    ) {}

    protected function value(): array
    {
        return [
            'rate' => [
                'unit' => 'year',
            ],
        ];
    }
}
