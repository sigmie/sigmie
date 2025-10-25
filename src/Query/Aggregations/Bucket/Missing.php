<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class Missing extends Bucket
{
    public function __construct(
        protected string $name,
        protected string $field
    ) {}

    protected function value(): array
    {
        return [
            'missing' => [
                'field' => $this->field,
            ],
        ];
    }
}
