<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Pipeline;

class SumBucket extends Pipeline
{
    public function __construct(
        protected string $name,
        protected string $path
    ) {
        parent::__construct($this->name, 'sum_bucket', $this->path);
    }
}
