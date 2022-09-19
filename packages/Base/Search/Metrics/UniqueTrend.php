<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use Sigmie\Base\Search\Aggregations\Metrics\Metric;
use Sigmie\Base\Search\Aggs;

class UniqueTrend extends Trend
{
    protected function aggregation(Aggs $aggs): Metric
    {
        return $aggs->cardinality($this->trendName, $this->metricField);
    }
}
