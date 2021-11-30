<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

use Sigmie\Base\Shared\Missing;

class Cardinality extends Metric
{
    use Missing;

    protected function value(): array
    {
        $value =  [
            'cardinality' => [
                'field' => $this->field,
            ]
        ];

        if (isset($this->missing)) {
            $value['cardinality']['missing'] = $this->missing;
        }

        return $value;
    }
}
