<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Pipeline;

use Sigmie\Query\Contracts\Aggregation;
use Sigmie\Query\Shared\Meta;

use function Sigmie\Functions\object_map;

abstract class Pipeline implements Aggregation
{
    use Meta;

    public function __construct(
        protected string $name,
        protected string $type,
        protected string $path
    ) {}

    public function toRaw(): array
    {
        $raw = [$this->name => [
            $this->type => [
                'buckets_path' => $this->path,
            ],
        ]];

        if ($this->meta !== []) {
            $raw[$this->name]['meta'] = object_map($this->meta);
        }

        return $raw;
    }
}
