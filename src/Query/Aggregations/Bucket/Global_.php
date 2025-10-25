<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class Global_ extends Bucket
{
    public function __construct(
        protected string $name,
    ) {}

    protected function value(): array
    {
        return [
            'global' => (object) [],
        ];
    }
}
