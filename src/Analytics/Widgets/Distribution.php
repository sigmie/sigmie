<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Query\Aggs;

/**
 * A histogram of a numeric field — the distribution chart ("order-size spread",
 * "response-time buckets"). Buckets are fixed-width by $interval.
 */
class Distribution extends Widget
{
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected string $field,
        protected int $interval,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            // Empty buckets count towards search.max_buckets, so one outlier can fail the
            // request. Elasticsearch returns the filled buckets and extract() adds the empty ones.
            $aggs->histogram('buckets', $this->field, $this->interval, minDocCount: 1);
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $buckets = [];
        $next = null;
        foreach ($aggregations[$this->name]['buckets']['buckets'] ?? [] as $bucket) {
            $step = (int) round($bucket['key'] / $this->interval);
            for (; $next !== null && $next < $step; $next++) {
                $buckets[] = ['label' => (float) ($next * $this->interval), 'count' => 0];
            }

            $buckets[] = ['label' => $bucket['key'], 'count' => $bucket['doc_count'] ?? 0];
            $next = $step + 1;
        }

        return [
            'type' => 'distribution',
            'field' => $this->field,
            'interval' => $this->interval,
            'buckets' => $buckets,
        ];
    }
}
