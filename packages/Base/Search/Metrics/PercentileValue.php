<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use Sigmie\Base\Contracts\ToRaw;
use Sigmie\Base\Search\Aggregations\Metrics\PercentileRanks;

class PercentileValue implements ToRaw
{
    protected int|float $number;

    public function __construct(
        protected string $name,
        protected string $field,
    ) {
    }

    public function extract(array $aggregations)
    {
        return [$this->name => [
            'label' => null,
            'value' => array_values($aggregations[$this->name]['values'])[0],
        ]];
    }

    public function under(int|float $value)
    {
        $this->value = $value;
    }

    public function toRaw(): array
    {
        $aggregation = new PercentileRanks($this->name, $this->field, [$this->value]);

        return $aggregation->toRaw();
    }
}
