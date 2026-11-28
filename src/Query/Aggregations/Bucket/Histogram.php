<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Generator;
use InvalidArgumentException;

class Histogram extends Bucket
{
    // The most buckets dense() returns. A filled bucket costs up to 400 bytes in PHP.
    public const MAX_DENSE_BUCKETS = 100_000;

    /**
     * The buckets of a `min_doc_count: 1` histogram as [key, bucket], with a null bucket for
     * each empty step between them. Without an interval the buckets pass through.
     *
     * @return Generator<int, array{0: int|float, 1: array|null}>
     */
    public static function dense(array $buckets, int|float|null $interval): Generator
    {
        if ($interval !== null && $buckets !== []) {
            $span = ($buckets[array_key_last($buckets)]['key'] - $buckets[array_key_first($buckets)]['key']) / $interval;

            if ($span >= self::MAX_DENSE_BUCKETS) {
                throw new InvalidArgumentException(sprintf(
                    'A histogram with an interval of %s spans %s buckets, above the limit of %s. Use a larger interval.',
                    $interval, number_format($span + 1), number_format(self::MAX_DENSE_BUCKETS),
                ));
            }
        }

        $next = null;
        foreach ($buckets as $bucket) {
            $step = $interval === null ? null : (int) round($bucket['key'] / $interval);
            // Elasticsearch keys an empty bucket as its step times the interval, in doubles.
            for (; $next !== null && $next < $step; $next++) {
                yield [(float) ($next * $interval), null];
            }

            yield [$bucket['key'], $bucket];
            $next = $step === null ? null : $step + 1;
        }
    }

    public function __construct(
        protected string $name,
        protected string $field,
        protected int|float $interval,
        protected int $minDocCount = 0,
        protected ?array $extendedBounds = null
    ) {}

    protected function value(): array
    {
        $value = [
            'histogram' => [
                'field' => $this->field,
                'interval' => $this->interval,
                'min_doc_count' => $this->minDocCount,
            ],
        ];

        if ($this->extendedBounds) {
            $value['histogram']['extended_bounds'] = $this->extendedBounds;
        }

        return $value;
    }
}
