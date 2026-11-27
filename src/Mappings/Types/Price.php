<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Traits\HasFacets;
use Sigmie\Parse\ParseException;
use Sigmie\Query\Aggs;

class Price extends Type
{
    use HasFacets;

    protected string $type = 'scaled_float';

    public function toRaw(): array
    {
        $raw = parent::toRaw();
        $raw[$this->name]['scaling_factor'] ??= 100;

        return $raw;
    }

    public function queries(array|string $queryString): array
    {
        // It's unlikely to search in an input field
        // for a price.

        // Price type is better for range filters

        return [];
    }

    public function aggregation(Aggs $aggs, string $param): void
    {
        [$interval] = explode(',', $param);
        if (! is_numeric($interval) || ! is_finite((float) $interval) || $interval <= 0) {
            throw new ParseException('Price histogram interval must be a positive number.');
        }

        $aggs->histogram(
            $this->name().'_histogram',
            $this->name(),
            interval: $interval + 0
        );

        $aggs->min($this->name().'_min', $this->name());
        $aggs->max($this->name().'_max', $this->name());
    }

    public function facets(array $aggregation): ?array
    {
        $originalBuckets = $aggregation[$this->name().'_histogram']['buckets'] ?? [];

        $min = $aggregation[$this->name().'_min']['value'] ?? null;
        $max = $aggregation[$this->name().'_max']['value'] ?? null;

        $histogram = [];
        foreach ($originalBuckets as $bucket) {
            $bound = $bucket['key'];
            $histogram[is_float($bound) ? json_encode($bound, JSON_THROW_ON_ERROR) : $bound] = $bucket['doc_count'];
        }

        // Keys 0, 1, 2… are a PHP list. json_encode would drop those lower bounds.
        if ($histogram !== [] && array_is_list($histogram)) {
            $histogram = (object) $histogram;
        }

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
        if (! $this->ignoresMalformedScalar($value) && ! Number::fitsMapping($this->type, $value, (bool) ($this->storedMapping['coerce'] ?? true))) {
            return [false, sprintf('The field %s mapped as price must be a number', $key)];
        }

        return [true, ''];
    }
}
