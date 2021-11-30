<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Metrics;

use Sigmie\Base\Contracts\Aggregation;
use Sigmie\Base\Shared\Meta;

abstract class Metric implements Aggregation
{
    use Meta;

    public function __construct(
        protected string $name,
        protected string $field
    ) {
    }

    abstract protected function value();

    public function toRaw(): array
    {
        return [$this->name => [
            'meta' => (object) $this->meta,
            ...$this->value()
        ]];
    }
}
