<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

use Sigmie\Base\Shared\Missing;

class Stats extends Metric
{
    use Missing;

    protected function value(): array
    {
        $value =  [
            'stats' => [
                'field' => $this->field,
            ],
        ];

        if (isset($this->missing)) {
            $value['stats']['missing'] = $this->missing;
        }

        return $value;
    }
}
