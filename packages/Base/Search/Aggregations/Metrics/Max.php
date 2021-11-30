<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

use Sigmie\Base\Shared\Missing;

class Max extends Metric
{
    use Missing;

    protected function value(): array
    {
        $value =  [
            'max' => [
                'field' => $this->field,
            ]
        ];

        if (isset($this->missing)) {
            $value['min']['missing'] = $this->missing;
        }

        return $value;
    }
}
