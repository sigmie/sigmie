<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use Sigmie\Base\Search\Aggregations\Metrics\Metric;
use Sigmie\Base\Search\Aggs;

abstract class Score
{
    protected string $by;

    public function __construct(
        protected string $name,
        protected string $field,
        protected int $size
    ) {
    }

    abstract protected function aggregation(Aggs $aggs): Metric;

    public function extract(array $aggregations): array
    {
        return $aggregations;
    }
}
