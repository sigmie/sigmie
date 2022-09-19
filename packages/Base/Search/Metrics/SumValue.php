<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use Sigmie\Base\Search\Aggregations\Pipeline\Pipeline;
use Sigmie\Base\Search\Aggregations\Pipeline\SumBucket;

class SumValue extends TrendValue
{
    protected function bucketAggregation(string $trendPath): Pipeline
    {
        return new SumBucket($this->name, $trendPath);
    }
}
