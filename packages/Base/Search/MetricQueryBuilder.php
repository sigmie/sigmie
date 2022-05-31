<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use DateTime;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Contracts\Aggs as AggregationsInterface;
use Sigmie\Base\Contracts\ToRaw;
use Sigmie\Base\Search\Aggregations\Enums\CalendarInterval;
use Sigmie\Base\Search\Aggs;
use Sigmie\Base\Search\Metrics\Scores;
use Sigmie\Base\Search\Metrics\Trends;
use Sigmie\Base\Search\Metrics\Values;
use Sigmie\Base\Search\Queries\Compound\Boolean;

class MetricQueryBuilder
{
    use Search;

    protected array $filters = [];

    protected Trends $trends;

    protected Values $values;

    protected Scores $scores;

    protected string $format = 'Y-m-d\TH:i:s.Z';

    public function __construct(
        protected SearchBuilder $searchBuilder,
        protected string $timestampField,
        protected DateTime $from = new DateTime('-30 days'),
        protected DateTime $to = new DateTime(),
    ) {
        $this->trends = new Trends($this->timestampField);
        $this->values = new Values($this->timestampField, $this->trends);
        $this->scores = new Scores();
    }

    public function format(string $format)
    {
        $this->format = $format;

        return $this;
    }

    public function from(DateTime $from)
    {
        $this->from = $from;

        return $this;
    }

    public function to(DateTime $to)
    {
        $this->to = $to;

        return $this;
    }

    public function filter(string $field, string $value)
    {
        $this->filters[$field] = $value;

        return $this;
    }

    public function trends(callable $callable)
    {
        $callable($this->trends);

        return $this;
    }

    public function values(callable $callable)
    {
        $callable($this->values);

        return $this;
    }

    public function scores(callable $callable)
    {
        $callable($this->scores);

        return $this;
    }

    public function get()
    {
        $x = $this->searchBuilder->bool(function (Boolean $boolean) {
            foreach ($this->filters as $field => $value) {
                $boolean->filter()->term($field, $value);
            }

            $boolean->filter()->range('date', [
                '>=' => $this->from->format($this->format),
                '<=' => $this->to->format($this->format)
            ]);
        })
            ->size(0)
            ->aggregate(function (Aggs $aggs) {
                $aggs->add($this->trends);
                $aggs->add($this->values);
                $aggs->add($this->scores);
            });


        $aggregations = $x->response()->json('aggregations');

        $result = [
            'trends' => $this->trends->extract($aggregations),
            'values' => $this->values->extract($aggregations),
            'scores' => $this->scores->extract($aggregations)
        ];

        return $result;
    }
}
