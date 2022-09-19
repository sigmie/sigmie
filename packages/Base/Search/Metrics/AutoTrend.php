<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use Sigmie\Base\Search\Aggregations\Bucket\AutoDateHistogram;
use Sigmie\Base\Search\Aggregations\Enums\MinimumInterval;
use Sigmie\Base\Search\Aggregations\Metrics\Metric;
use Sigmie\Base\Search\Aggs;
use Sigmie\Support\Collection;

abstract class AutoTrend extends AutoDateHistogram
{
    protected string $alias;

    protected MinimumInterval $minimumInterval = MinimumInterval::Second;

    public function __construct(
        protected string $trendName,
        protected string $metricField,
        protected string $timestampField,
        protected string $histogramName,
        protected int $buckets,
    ) {
        parent::__construct("{$trendName}_histogram", $this->timestampField, $buckets);

        $this->aggregate(fn (Aggs $aggs) => $this->aggregation($aggs));
    }

    abstract protected function aggregation(Aggs $aggs): Metric;

    public function extract(array $aggregations): array
    {
        $collection = new Collection($aggregations["{$this->trendName}_histogram"]['buckets'] ?? []);


        $res = $collection->map(fn (array $bucket) =>
        [
            'label' => $bucket['key_as_string'],
            'value' => $bucket[$this->trendName]['value']
        ]);

        return [$this->trendName => [
            'values' => $res->toArray(),
            'interval' => $aggregations["{$this->trendName}_histogram"]['interval']
        ]];
    }

    public function minimumIntervalMinute()
    {
        $this->minimumInterval = MinimumInterval::Minute;
    }

    public function minimumIntervalHour()
    {
        $this->minimumInterval = MinimumInterval::Hour;
    }

    public function minimumIntervalDay()
    {
        $this->minimumInterval = MinimumInterval::Day;
    }

    public function minimumIntervalMonth()
    {
        $this->minimumInterval = MinimumInterval::Month;
    }

    public function minimumIntervalYear()
    {
        $this->minimumInterval = MinimumInterval::Year;
    }

    public function minimumIntervalSeconds()
    {
        $this->minimumInterval = MinimumInterval::Second;
    }
}
