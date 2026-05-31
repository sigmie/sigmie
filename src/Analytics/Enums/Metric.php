<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Enums;

use Sigmie\Query\Aggs;

/**
 * The metric an analytics widget computes. Each case knows how to attach the matching
 * Elasticsearch metric sub-aggregation and how to read its value back out of the response.
 */
enum Metric: string
{
    case Sum = 'sum';

    case Avg = 'avg';

    case Min = 'min';

    case Max = 'max';

    case Count = 'count';

    case Unique = 'unique';

    case Median = 'median';

    /**
     * Attach the metric as a sub-aggregation named $name on the given field.
     */
    public function apply(Aggs $aggs, string $name, string $field): void
    {
        match ($this) {
            self::Sum => $aggs->sum($name, $field),
            self::Avg => $aggs->avg($name, $field),
            self::Min => $aggs->min($name, $field),
            self::Max => $aggs->max($name, $field),
            self::Count => $aggs->valueCount($name, $field),
            self::Unique => $aggs->cardinality($name, $field),
            self::Median => $aggs->percentiles($name, $field, [50]),
        };
    }

    /**
     * Read the metric's value out of its slice of the aggregation response.
     */
    public function extract(array $aggregation): int|float|null
    {
        if ($this === self::Median) {
            return $aggregation['values']['50.0'] ?? null;
        }

        return $aggregation['value'] ?? null;
    }

    /**
     * The buckets_path / order key Elasticsearch uses to reference this metric.
     */
    public function orderKey(string $name): string
    {
        return $this === self::Median ? $name.'.50' : $name;
    }
}
