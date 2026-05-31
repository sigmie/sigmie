<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Pipeline;

class CumulativeSum extends Pipeline
{
    public function __construct(
        protected string $name,
        protected string $path
    ) {
        parent::__construct($this->name, 'cumulative_sum', $this->path);
    }
}
