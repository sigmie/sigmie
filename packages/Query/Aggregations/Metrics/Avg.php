<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Shared\Missing;

class Avg extends Metric
{
    use Missing;

    protected function value(): array
    {
        $value =  [
            'avg' => [
                'field' => $this->field,
            ],
        ];

        if (isset($this->missing)) {
            $value['avg']['missing'] = $this->missing;
        }

        return $value;
    }
}
