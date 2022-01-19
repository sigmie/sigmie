<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Bucket;

class Missing extends Bucket
{
    public function __construct(
        protected string $name,
        protected string $field
    ) {
    }

    public function value(): array
    {
        return [
            'missing' => [
                'field' => $this->field,
            ],
        ];
    }
}
