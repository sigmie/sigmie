<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use Sigmie\Base\Search\Aggregations\Pipeline\AvgBucket;
use Sigmie\Base\Search\Aggregations\Pipeline\Pipeline;

class AvgValue extends TrendValue
{
    protected function bucketAggregation(string $trendPath): Pipeline
    {
        return new AvgBucket($this->name, $trendPath);
    }
}
