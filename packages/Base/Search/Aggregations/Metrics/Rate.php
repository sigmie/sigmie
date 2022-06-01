<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

use Sigmie\Base\Shared\Missing;

class Rate extends Metric
{
    public function __construct(
        protected string $name,
        protected string $field,
    ) {
    }

    protected function value(): array
    {
        $value =  [
            'rate' => [
                'unit' => 'year'
            ],
        ];

        return $value;
    }
}
