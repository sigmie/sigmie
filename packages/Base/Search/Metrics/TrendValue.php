<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use League\CommonMark\Util\ArrayCollection;
use Sigmie\Base\Contracts\Aggregation;
use Sigmie\Base\Contracts\ToRaw;
use Sigmie\Base\Search\Aggregations\Bucket\DateHistogram;
use Sigmie\Base\Search\Aggregations\Enums\CalendarInterval;
use Sigmie\Base\Search\Aggregations\Metrics\Metric;
use Sigmie\Base\Search\Aggregations\Pipeline\Pipeline;
use Sigmie\Base\Search\Aggs;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as ContractsCollection;

abstract class TrendValue
{
    protected string $alias;

    public function __construct(
        protected string $name,
        protected string $trendName,
    ) {
    }

    abstract protected function bucketAggregation(string $trendPath): Pipeline;

    public function extract(array $aggregations): array
    {
        return [$this->name => [
            'value' => $aggregations[$this->name]['value'] ?? null,
            'label' => $aggregations[$this->name]['keys'][0] ?? null
        ]];
    }

    public function toRaw(): array
    {
        $bucketPath = "{$this->trendName}_histogram>{$this->trendName}";

        return [...$this->bucketAggregation($bucketPath)->toRaw()];
    }
}
