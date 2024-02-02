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

    public function queries(string $queryString): array
    {
        $queries = [];

        // It's unlikely to search in an input field
        // for a price.

        // Price type is better for range filters

        return $queries;
    }

    public function aggregation(Aggs $aggs, string|int $param): void
    {
        $aggs->histogram("{$this->name()}_histogram", $this->name(), interval: $param);
        $aggs->min("{$this->name()}_min", $this->name());
        $aggs->max("{$this->name()}_max", $this->name());
    }

    public function facets(ElasticsearchResponse $response): array
    {
        $originalBuckets = $response->json("aggregations.{$this->name()}_histogram")['buckets'] ?? [];

        $histogram = array_column($originalBuckets, 'doc_count', 'key');

        $min = $response->json("aggregations.{$this->name()}_min");

        $max = $response->json("aggregations.{$this->name()}_max");

        return [
            'min' => $min['value'],
            'max' => $max['value'],
            'histogram' => $histogram
        ];
    }

    public function isFacetable(): bool
    {
        return true;
    }
}
