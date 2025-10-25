<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

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
                'buckets_path' => $this->bucketsPath,
                'script' => $this->script,
            ],
        ];
    }
}
