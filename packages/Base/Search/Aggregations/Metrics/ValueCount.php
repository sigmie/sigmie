<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

use Sigmie\Base\Shared\Missing;

class ValueCount extends Metric
{
    protected function value(): array
    {
        $value =  [
            'value_count' => [
                'field' => $this->field,
            ]
        ];

        return $value;
    }
}
