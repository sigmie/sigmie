<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Query\Aggs;

class Price extends Type
{
    public function toRaw(): array
    {
        $raw = [$this->name => [
            'type' => 'scaled_float',
            'scaling_factor' => 100,
        ]];

        $raw[$this->name]['meta'] =
            [
                ...$this->meta,
                'class' => static::class,
            ];

        return $raw;
    }

    public function queries(array|string $queryString): array
    {
        $queries = [];

        // It's unlikely to search in an input field
        // for a price.

        // Price type is better for range filters

        return $queries;
    }

    public function aggregation(Aggs $aggs, string $param): void
    {
        [$interval] = explode(',', $param);

        $aggs->histogram(
            "{$this->name()}_histogram",
            $this->name(),
            interval: (int) $interval
        );

        $aggs->min("{$this->name()}_min", $this->name());
        $aggs->max("{$this->name()}_max", $this->name());
    }

    public function facets(array $aggregation): ?array
    {
        $originalBuckets = $aggregation[$this->name()][$this->name()][$this->name() . '_histogram']['buckets'] ?? $aggregation[$this->name()][$this->name() . '_histogram']['buckets'] ?? [];

        $min = $aggregation[$this->name()][$this->name()][$this->name() . '_min']['value'] ?? $aggregation[$this->name()][$this->name() . '_min']['value'] ?? 0;
        $max = $aggregation[$this->name()][$this->name()][$this->name() . '_max']['value'] ?? $aggregation[$this->name()][$this->name() . '_max']['value'] ?? 0;

        $histogram = array_column($originalBuckets, 'doc_count', 'key');

        return [
            'min' => $min,
            'max' => $max,
            'histogram' => $histogram,
        ];
    }

    public function isFacetable(): bool
    {
        return true;
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_numeric($value)) {
            return [false, "The field {$key} mapped as price must be a number"];
        }

        return [true, ''];
    }
}
