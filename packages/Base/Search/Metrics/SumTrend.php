<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use Sigmie\Base\Contracts\Aggregation;
use Sigmie\Base\Contracts\ToRaw;
use Sigmie\Base\Search\Aggregations\Bucket\DateHistogram;
use Sigmie\Base\Search\Aggregations\Enums\CalendarInterval;
use Sigmie\Base\Search\Aggregations\Metrics\Metric;
use Sigmie\Base\Search\Aggregations\Metrics\Sum;
use Sigmie\Base\Search\Aggs;

class SumTrend extends Trend
{
    protected function aggregation(Aggs $aggs): Metric
    {
        return $aggs->sum($this->trendName, $this->metricField);
    }
}
