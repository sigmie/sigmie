<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Enums;

use Sigmie\Query\Aggregations\Bucket\MultiTerms;
use Sigmie\Query\Aggregations\Bucket\Terms;
use Sigmie\Query\Aggregations\Bucket\UnionTerms;
use Sigmie\Query\Aggregations\Metrics\Max;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Term\Exists;

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
     * Distinct-count accuracy for the Unique metric. A dashboard "distinct users / orders / …"
     * headline is misleading if it silently approximates, so analytics asks Elasticsearch for the
     * maximum exact range (40000) rather than its cheap default (3000). Raw cardinality() callers
     * outside analytics keep the Elasticsearch default.
     */
    private const UNIQUE_PRECISION_THRESHOLD = 40000;

    /**
     * Attach the metric as a sub-aggregation named $name on the given field.
     * A count without a field counts the documents that contain $countedField.
     */
    public function apply(Aggs $aggs, string $name, string $field, string $countedField = ''): void
    {
        match ($this) {
            self::Sum => $aggs->sum($name, $field),
            self::Avg => $aggs->avg($name, $field),
            self::Min => $aggs->min($name, $field),
            self::Max => $aggs->max($name, $field),
            self::Count => $field === ''
                ? $aggs->filter($name, new Exists($countedField))
                : $aggs->valueCount($name, $field),
            self::Unique => $aggs->cardinality($name, $field)->precisionThreshold(self::UNIQUE_PRECISION_THRESHOLD),
            self::Median => $aggs->percentiles($name, $field, [50]),
        };
    }

    /**
     * Read the metric's value out of its slice of the aggregation response.
     */
    public function extract(array $aggregation): int|float|null
    {
        $value = match ($this) {
            self::Count => $aggregation['value'] ?? $aggregation['doc_count'] ?? null,
            self::Median => $aggregation['values']['50.0'] ?? null,
            default => $aggregation['value'] ?? null,
        };

        return is_int($value) || (is_float($value) && is_finite($value)) ? $value : null;
    }

    /**
     * The field a result reports: a count without a field reports the field it counted.
     */
    public function resultField(string $field, string $countedField): string
    {
        return $this === self::Count && $field === '' ? $countedField : $field;
    }

    /** @return array{document_count: int, value_count: int, field: string} */
    public function population(array $bucket, string $field): array
    {
        $valueCount = $this === self::Count ? $this->extract($bucket['metric'] ?? []) : ($bucket['metric_population']['value'] ?? 0);

        return [
            'document_count' => (int) ($bucket['doc_count'] ?? 0),
            'value_count' => (int) $valueCount,
            'field' => $field,
        ];
    }

    /**
     * The Elasticsearch bucket order key for this metric.
     */
    public function orderKey(string $name): string
    {
        return $this === self::Median ? $name.'.50' : $name;
    }

    public function rank(Terms|MultiTerms|UnionTerms $terms, string $name, string $field, string $direction, string $presence = 'metric_present'): void
    {
        $key = $this->orderKey($name);

        $missingFirst = match ($this) {
            self::Avg, self::Min, self::Median => $direction === 'desc',
            self::Max => $direction === 'asc',
            default => false,
        };

        if (! $missingFirst) {
            $terms->order($key, $direction);

            return;
        }

        // Elasticsearch ranks empty buckets by a metric-specific sentinel (NaN or infinity).
        // Every measured group has the same primary key, regardless of its population.
        $terms->aggregate(fn (Aggs $aggs): Max => $aggs->max($presence, $field)->script('1'));
        $terms->orderBy([$presence => 'desc', $key => $direction]);
    }

    public function pipelinePath(string $name, string $field): string
    {
        return $this === self::Count && $field === '' ? $name.'>_count' : $this->orderKey($name);
    }
}
