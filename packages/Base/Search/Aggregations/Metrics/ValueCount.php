<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

class ValueCount extends Metric
{
    protected function value(): array
    {
        return [
            'value_count' => [
                'field' => $this->field,
            ]
        ];
    }
}
