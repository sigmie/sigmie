<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Shared\Meta;
use Sigmie\Query\Shared\Missing;

class Min extends Metric
{
    use Missing, Meta;

    protected function value(): array
    {
        $value =  [
            'min' => [
                'field' => $this->field,
            ],
        ];

        if (isset($this->missing)) {
            $value['min']['missing'] = $this->missing;
        }

        return $value;
    }
}
