<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

use Sigmie\Base\Shared\Missing;

class PercentileRanks extends Metric
{
    use Missing;

    public function __construct(
        protected string $name,
        protected string $field,
        protected array $values
    ) {
    }

    protected function value(): array
    {
        $value =  [
            'percentile_ranks' => [
                'field' => $this->field,
                'values' => $this->values,
            ]
        ];

        if (isset($this->missing)) {
            $value['percentile_ranks']['missing'] = $this->missing;
        }

        return $value;
    }
}
