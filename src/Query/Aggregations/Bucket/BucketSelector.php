<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class BucketSelector extends Bucket
{
    public function __construct(
        protected string $name,
        protected array $bucketPath,
        protected string $script,
    ) {
        parent::__construct($name);
    }

    public function value(): array
    {
        return [
            "bucket_selector" => [
                "buckets_path" => $this->bucketPath,
                "script" => $this->script
            ]
        ];
    }
}
