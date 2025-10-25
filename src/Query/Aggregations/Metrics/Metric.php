<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Contracts\Aggregation;
use Sigmie\Query\Shared\Meta;

abstract class Metric implements Aggregation
{
    use Meta;

    public function __construct(
        protected string $name,
        protected string $field
    ) {}

    public function toRaw(): array
    {
        $raw = [$this->name => [
            ...$this->value(),
        ]];

        if ($this->meta !== []) {
            $raw[$this->name]['meta'] = [
                ...$this->meta,
            ];
        }

        return $raw;
    }

    abstract protected function value();
}
