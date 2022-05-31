<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use Sigmie\Base\Search\Aggregations\Bucket\Terms;
use Sigmie\Base\Search\Aggregations\Enums\CalendarInterval;
use Sigmie\Base\Search\Aggregations\Metrics\Metric;
use Sigmie\Base\Search\Aggregations\Pipeline\Pipeline;
use Sigmie\Base\Search\Aggs;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as ContractsCollection;

abstract class Score
{
    protected string $by;

    public function __construct(
        protected string $name,
        protected string $field,
        protected int $size
    ) {
    }

    abstract protected function aggregation(Aggs $aggs): Metric;

    public function extract(array $aggregations): array
    {
        return $aggregations;
    }
}
