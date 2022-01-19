<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

use Sigmie\Base\Shared\Missing;

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
