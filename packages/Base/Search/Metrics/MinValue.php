<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use Sigmie\Base\Search\Aggregations\Pipeline\MinBucket;
use Sigmie\Base\Search\Aggregations\Pipeline\Pipeline;

class MinValue extends TrendValue
{
    protected function bucketAggregation(string $trendPath): Pipeline
    {
        return new MinBucket($this->name, $trendPath);
    }
}
