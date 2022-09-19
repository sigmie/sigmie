<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Metrics;

use Sigmie\Base\Search\Aggregations\Pipeline\Pipeline;

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
