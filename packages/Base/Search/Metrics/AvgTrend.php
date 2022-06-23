<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use Sigmie\Base\Search\Aggregations\Metrics\Metric;
use Sigmie\Base\Search\Aggs;

class AvgTrend extends Trend
{
    protected function aggregation(Aggs $aggs): Metric
    {
        $field = $aggs->avg($this->trendName, $this->metricField);

        return $field;
    }
}
