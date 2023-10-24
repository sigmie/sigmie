<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Pipeline;

use Sigmie\Query\Contracts\Aggregation;

abstract class Pipeline implements Aggregation
{
    public function __construct(
        protected string $name,
        protected string $type,
        protected string $path
    ) {
    }

    public function toRaw(): array
    {
        $raw = [$this->name => [
            $this->type => [
                'buckets_path' => $this->path,
            ],
        ]];

        return $raw;
    }
}
