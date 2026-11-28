<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class ReverseNested extends Bucket
{
    public function __construct(
        protected string $name,
    ) {}

    protected function value(): array
    {
        return [
            'reverse_nested' => (object) [],
        ];
    }
}
