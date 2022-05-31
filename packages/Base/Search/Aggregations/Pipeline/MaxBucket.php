<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Pipeline;

class MaxBucket extends Pipeline
{
    public function __construct(
        protected string $name,
        protected string $path
    ) {
        parent::__construct($this->name, 'max_bucket', $this->path);
    }
}
