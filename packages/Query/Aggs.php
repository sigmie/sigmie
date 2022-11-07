<?php

declare(strict_types=1);

namespace Sigmie\Query;

use Sigmie\Query\Contracts\Aggs as AggsInterface;
use Sigmie\Query\Aggregations\Bucket\DateHistogram;
use Sigmie\Query\Aggregations\Bucket\Missing;
use Sigmie\Query\Aggregations\Bucket\Range;
use Sigmie\Query\Aggregations\Bucket\SignificantText;
use Sigmie\Query\Aggregations\Bucket\Terms;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Aggregations\Metrics\Avg;
use Sigmie\Query\Aggregations\Metrics\Cardinality;
use Sigmie\Query\Aggregations\Metrics\Composite;
use Sigmie\Query\Aggregations\Metrics\Max;
use Sigmie\Query\Aggregations\Metrics\Min;
use Sigmie\Query\Aggregations\Metrics\PercentileRanks;
use Sigmie\Query\Aggregations\Metrics\Percentiles;
use Sigmie\Query\Aggregations\Metrics\Rate;
use Sigmie\Query\Aggregations\Metrics\Stats;
use Sigmie\Query\Aggregations\Metrics\Sum;
use Sigmie\Query\Aggregations\Metrics\ValueCount;

class Aggs implements AggsInterface
{
    protected array $aggs = [];

    public function add($aggs)
    {
        $this->aggs[] = $aggs;

        return $this;
    }


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

    public function rate(string $name, string $field): Rate
    {
        $aggregation = new Rate($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function percentileRanks(string $name, string $field, array $values): PercentileRanks
    {
        $aggregation = new PercentileRanks($name, $field, $values);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function composite(string $name, array $sources): Composite
    {
        $aggregation = new Composite($name, $sources);

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
