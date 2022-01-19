<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

use Sigmie\Base\Shared\Missing;

class Percentiles extends Metric
{
    use Missing;

    public function __construct(
        protected string $name,
        protected string $field,
        protected array $percents = [1, 5, 25, 50, 75, 95, 99]
    ) {
    }

    protected function value(): array
    {
        $value =  [
            'percentiles' => [
                'field' => $this->field,
                'percents' => $this->percents,
            ],
        ];

        if (isset($this->missing)) {
            $value['percentiles']['missing'] = $this->missing;
        }

        return $value;
    }
}
