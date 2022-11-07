<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Contracts\Aggregation;
use Sigmie\Query\Shared\Meta;

abstract class Metric implements Aggregation
{
    public function __construct(
        protected string $name,
        protected string $field
    ) {
    }

    public function toRaw(): array
    {
        return [$this->name => [
            ...$this->value(),
        ]];
    }

    abstract protected function value();
}
