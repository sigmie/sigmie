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
        protected array $sources,
        protected int $size = 10,
        protected ?array $after = null,
    ) {}

    protected function value(): array
    {
        $value = [
            'composite' => [
                'sources' => $this->sources,
                'size' => $this->size,
            ],
        ];

        if ($this->after) {
            $value['composite']['after'] = (object) $this->after;
        }

        return $value;
    }
}
