<?php

declare(strict_types=1);

namespace Sigmie\Query;

use Sigmie\Query\Aggregations\Bucket\AutoDateHistogram;
use Sigmie\Query\Aggregations\Bucket\BucketSelector;
use Sigmie\Query\Aggregations\Bucket\DateHistogram;
use Sigmie\Query\Aggregations\Bucket\Filter;
use Sigmie\Query\Aggregations\Bucket\Global_;
use Sigmie\Query\Aggregations\Bucket\Histogram;
use Sigmie\Query\Aggregations\Bucket\Missing;
use Sigmie\Query\Aggregations\Bucket\Nested;
use Sigmie\Query\Aggregations\Bucket\Range;
use Sigmie\Query\Aggregations\Bucket\RangeFilter;
use Sigmie\Query\Aggregations\Bucket\SignificantText;
use Sigmie\Query\Aggregations\Bucket\Sort;
use Sigmie\Query\Aggregations\Bucket\TermFilter;
use Sigmie\Query\Aggregations\Bucket\Terms;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Aggregations\Enums\MinimumInterval;
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
use Sigmie\Query\Contracts\Aggs as AggsInterface;

class Aggs implements AggsInterface
{
    protected array $aggs = [];

    public function add($aggs)
    {
        $this->aggs[] = $aggs;

        return $this;
    }

    public function nested(string $name, string $path, callable $callable)
    {
        $aggs = new Aggs;

        $callable($aggs);

        $aggregation = new Nested($name, $path, $aggs);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function filter(
        string $name,
        // string $field,
        //TODO add cast
        $query,
    ): Filter {
        $aggregation = new Filter($name, 
        // $field,
         $query);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function termFilter(
        string $name,
        string $field,
        string $term,
    ): TermFilter {
        return $this->filter($name, $field, $term);
    }

    public function rangeFilter(
        string $name,
        string $field,
        array $ranges,
    ): RangeFilter {

        $aggregation = new RangeFilter($name, $field, $ranges);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function bucketSelector(
        string $name,
        array $bucketsPath,
        string $script
    ): BucketSelector {

        $aggregation = new BucketSelector($name, $bucketsPath, $script);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function sort(
        string $name,
        array $sort,
        ?int $size = null,
        ?int $from = null,
    ): Sort {
        $aggregation = new Sort($name, $sort, $size, $from);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function histogram(
        string $name,
        string $field,
        int $interval,
        int $minDocCount = 0,
        ?array $extendedBounds = null
    ): Histogram {
        $aggregation = new Histogram($name, $field, $interval, $minDocCount, $extendedBounds);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function autoDateHistogram(
        string $name,
        string $field,
        int $buckets,
        MinimumInterval $minimumInterval = MinimumInterval::Second
    ) {
        $aggregation = new AutoDateHistogram($name, $field, $buckets, $minimumInterval);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function dateHistogram(
        string $name,
        string $field,
        CalendarInterval $interval,
        int $minDocCount = 0,
        ?array $extendedBounds = null

    ): DateHistogram {
        $aggregation = new DateHistogram($name, $field, $interval, $minDocCount, $extendedBounds);

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

    public function global(string $name): Global_
    {
        $aggregation = new Global_($name);

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

    public function composite(
        string $name,
        array $sources,
        int $size = 10,
        ?array $after = null,
    ): Composite {
        $aggregation = new Composite($name, $sources, $size, $after);

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
