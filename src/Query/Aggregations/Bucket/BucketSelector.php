<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use function Sigmie\Functions\object_map;

class BucketSelector extends Bucket
{
    public function __construct(
        protected string $name,
        protected array $bucketsPath,
        protected string $script,
    ) {
        parent::__construct($name);
    }

    protected function value(): array
    {
        return [
            'bucket_selector' => [
                'buckets_path' => object_map($this->bucketsPath),
                'script' => $this->script,
            ],
        ];
    }
}
