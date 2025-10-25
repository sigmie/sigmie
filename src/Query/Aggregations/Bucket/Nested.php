<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Contracts\Aggs;

class Nested extends Bucket
{
    public function __construct(
        protected string $name,
        protected string $path,
        protected Aggs $aggs,
    ) {}

    protected function value(): array
    {
        return [
            'nested' => [
                'path' => $this->path,
            ],
            'aggs' => $this->aggs->toRaw(),
        ];
    }
}
