<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Shared\Missing;

class Stats extends Metric
{
    use Missing;

    protected function value(): array
    {
        $value = [
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
