<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Aggregations\Bucket\Bucket;
use Sigmie\Query\Shared\Missing;

class Composite extends Bucket
{
    use Missing;

    public function __construct(
        protected string $name,
        protected array $sources
    ) {
    }

    protected function value(): array
    {
        $value = [
            'composite' => [
                'sources' => $this->sources,
            ],
        ];

        return $value;
    }
}
