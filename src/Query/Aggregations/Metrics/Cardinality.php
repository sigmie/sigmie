<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Shared\Missing;

class Cardinality extends Metric
{
    use Missing;

    protected function value(): array
    {
        $value = [
            'cardinality' => [
                'field' => $this->field,
            ],
        ];

        if (isset($this->missing)) {
            $value['cardinality']['missing'] = $this->missing;
        }

        return $value;
    }
}
