<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

use Sigmie\Base\Shared\Missing;

class Min extends Metric
{
    use Missing;

    protected function value(): array
    {
        $value =  [
            'min' => [
                'field' => $this->field,
            ]
        ];

        if (isset($this->missing)) {
            $value['min']['missing'] = $this->missing;
        }

        return $value;
    }
}
