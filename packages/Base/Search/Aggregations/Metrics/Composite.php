<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

use Sigmie\Base\Search\Aggregations\Bucket\Bucket;
use Sigmie\Base\Shared\Missing;

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
        $value =  [
            'composite' => [
                'sources' => $this->sources,
            ],
        ];

        return $value;
    }
}
