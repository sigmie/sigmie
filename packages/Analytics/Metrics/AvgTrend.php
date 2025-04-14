<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Metrics;

use Sigmie\Query\Aggregations\Metrics\Metric;
use Sigmie\Query\Aggs;

class AvgTrend extends Trend
{
    protected function aggregation(Aggs $aggs): Metric
    {
        $field = $aggs->avg($this->trendName, $this->metricField);

        return $field;
    }
}
