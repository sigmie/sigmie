<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

use Sigmie\Base\Shared\Missing;

class Sum extends Metric
{
    use Missing;

    protected function value(): array
    {
        $value =  [
            'sum' => [
                'field' => $this->field,
            ],
        ];

        if (isset($this->missing)) {
            $value['sum']['missing'] = $this->missing;
        }

        return $value;
    }
}
