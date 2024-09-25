<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class Filter extends Bucket
{
    public function __construct(
        protected string $name,
        protected string $field,
        protected string $term,
    ) {
        parent::__construct($name);
    }

    public function value(): array
    {
        return [
            'filter' => [
                'term' => [
                    $this->field => $this->term,
                ],
            ],
        ];
    }
}
