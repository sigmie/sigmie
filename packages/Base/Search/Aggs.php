<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Sigmie\Base\Contracts\Aggs as AggsInterface;
use Sigmie\Base\Search\Aggregations\Metrics\Avg;
use Sigmie\Base\Search\Aggregations\Metrics\Cardinality;
use Sigmie\Base\Search\Aggregations\Metrics\Max;
use Sigmie\Base\Search\Aggregations\Metrics\Min;
use Sigmie\Base\Search\Aggregations\Metrics\PercentileRanks;
use Sigmie\Base\Search\Aggregations\Metrics\Percentiles;
use Sigmie\Base\Search\Aggregations\Metrics\Stats;
use Sigmie\Base\Search\Aggregations\Metrics\Sum;
use Sigmie\Base\Search\Aggregations\Metrics\ValueCount;
use Sigmie\Base\Shared\Name;

class Aggs implements AggsInterface
{
    protected array $aggs = [];

    public function min(string $name, string $field): Min
    {
        $aggregation = new Min($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function stats(string $name, string $field, array $values)
    {
        $aggregation = new Stats($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function valueCount(string $name, string $field, array $values)
    {
        $aggregation = new ValueCount($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function sum(string $name, string $field)
    {
        $aggregation = new Sum($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function percentiles(string $name, string $field, array $percents = [1, 5, 25, 50, 75, 95, 99])
    {
        $aggregation = new Percentiles($name, $field, $percents);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function percentileRanks(string $name, string $field, array $values)
    {
        $aggregation = new PercentileRanks($name, $field, $values);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function cardinality(string $name, string $field)
    {
        $aggregation = new Cardinality($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function max(string $name, string $field)
    {
        $aggregation = new Max($name, $field);

        $this->aggs[] = $aggregation;

        return $aggregation;
    }

    public function avg(string $name, string $field)
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
