<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Bucket;

use Sigmie\Base\Contracts\Aggregation;
use Sigmie\Base\Contracts\Aggs as AggsInterface;
use Sigmie\Base\Search\Aggs;
use Sigmie\Base\Shared\Meta;

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
            'meta' => (object) $this->meta,
            ...$this->value(),
        ]];

        if (isset($this->aggs)) {
            $raw[$this->name]['aggs'] = $this->aggs->toRaw();
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
