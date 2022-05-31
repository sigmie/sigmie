<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use Sigmie\Base\Search\Aggregations\Metrics\Metric;
use Sigmie\Base\Search\Aggregations\Pipeline\MaxBucket;
use Sigmie\Base\Search\Aggregations\Pipeline\MinBucket;
use Sigmie\Base\Search\Aggregations\Pipeline\Pipeline;
use Sigmie\Base\Search\Aggs;

class MinValue extends Value
{
    protected function bucketAggregation(string $trendPath): Pipeline
    {
        return new MinBucket($this->name, $trendPath);
    }
}
