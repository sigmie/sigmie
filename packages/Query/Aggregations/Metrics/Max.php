<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Shared\Missing;

class Max extends Metric
{
    use Missing;

    protected function value(): array
    {
        $value =  [
            'max' => [
                'field' => $this->field,
            ],
        ];

        if (isset($this->missing)) {
            $value['min']['missing'] = $this->missing;
        }

        return $value;
    }
}
