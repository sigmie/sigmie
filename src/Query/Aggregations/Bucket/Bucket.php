<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Aggs;
use Sigmie\Query\Contracts\Aggregation;
use Sigmie\Query\Contracts\Aggs as AggsInterface;
use Sigmie\Query\Shared\Meta;

abstract class Bucket implements Aggregation
{
    use Meta;

    protected AggsInterface $aggs;

    public function __construct(
        protected string $name,
        protected string $field,
    ) {
    }

    public function toRaw(): array
    {
        $raw = [$this->name => [
            ...$this->value(),
        ]];

        if (isset($this->aggs)) {
            $raw[$this->name]['aggs'] = $this->aggs->toRaw();
        }

        if (count($this->meta) > 0) {
            $raw[$this->name]['meta'] = [
                ...$this->meta,
            ];
        }

        return $raw;
    }

    public function aggregate(callable $callable)
    {
        $this->aggs = new Aggs();

        $callable($this->aggs);

        return $this;
    }

    abstract protected function value();
}
