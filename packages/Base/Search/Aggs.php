<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Sigmie\Base\Contracts\Aggs as AggsInterface;
use Sigmie\Base\Search\Aggregations\Bucket\DateHistogram;
use Sigmie\Base\Search\Aggregations\Bucket\Missing;
use Sigmie\Base\Search\Aggregations\Bucket\Range;
use Sigmie\Base\Search\Aggregations\Bucket\SignificantText;
use Sigmie\Base\Search\Aggregations\Bucket\Terms;
use Sigmie\Base\Search\Aggregations\Enums\CalendarInterval;
use Sigmie\Base\Search\Aggregations\Metrics\Avg;
use Sigmie\Base\Search\Aggregations\Metrics\Cardinality;
use Sigmie\Base\Search\Aggregations\Metrics\Max;
use Sigmie\Base\Search\Aggregations\Metrics\Min;
use Sigmie\Base\Search\Aggregations\Metrics\PercentileRanks;
use Sigmie\Base\Search\Aggregations\Metrics\Percentiles;
use Sigmie\Base\Search\Aggregations\Metrics\Stats;
use Sigmie\Base\Search\Aggregations\Metrics\Sum;
use Sigmie\Base\Search\Aggregations\Metrics\ValueCount;

class Aggs implements AggsInterface
{
    protected array $aggs = [];

    public function dateHistogram(string $name, string $field, CalendarInterval $interval): DateHistogram
    {
        $aggregation = new DateHistogram($name, $field, $interval);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function range(string $name, string $field, array $ranges): Range
    {
        $aggregation = new Range($name, $field, $ranges);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function significantText(string $name, string $field): SignificantText
    {
        $aggregation = new SignificantText($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }


    public function terms(string $name, string $field): Terms
    {
        $aggregation = new Terms($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function missing(string $name, string $field): Missing
    {
        $aggregation = new Missing($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }


    public function min(string $name, string $field): Min
    {
        $aggregation = new Min($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function stats(string $name, string $field): Stats
    {
        $aggregation = new Stats($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function valueCount(string $name, string $field): ValueCount
    {
        $aggregation = new ValueCount($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function sum(string $name, string $field): Sum
    {
        $aggregation = new Sum($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function percentiles(string $name, string $field, array $percents = [1, 5, 25, 50, 75, 95, 99]): Percentiles
    {
        $aggregation = new Percentiles($name, $field, $percents);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function percentileRanks(string $name, string $field, array $values): PercentileRanks
    {
        $aggregation = new PercentileRanks($name, $field, $values);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function cardinality(string $name, string $field): Cardinality
    {
        $aggregation = new Cardinality($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function max(string $name, string $field): Max
    {
        $aggregation = new Max($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function avg(string $name, string $field): Avg
    {
        $aggregation = new Avg($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function toRaw(): array
    {
        $res = [];

        foreach ($this->aggs as $agg) {
            $res = [...$res, ...$agg->toRaw()];
        }
        return $res;
    }
}
