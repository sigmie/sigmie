<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

class Rate extends Metric
{
    public function __construct(
        protected string $name,
        protected string $field,
        protected ?string $unit = 'year',
    ) {}

    protected function value(): array
    {
        return [
            'rate' => [
                'field' => $this->field,
                ...($this->unit === null ? [] : ['unit' => $this->unit]),
            ],
        ];
    }
}
