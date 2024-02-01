<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

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
}
