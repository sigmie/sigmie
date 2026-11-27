<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Aggs;
use Sigmie\Query\Contracts\Aggregation;
use Sigmie\Query\Contracts\Aggs as AggsInterface;
use Sigmie\Query\Shared\Meta;

use function Sigmie\Functions\object_map;

abstract class Bucket implements Aggregation
{
    use Meta;

    protected AggsInterface $aggs;

    public function __construct(
        protected string $name,
    ) {}

    public function toRaw(): array
    {
        $raw = [$this->name => [
            ...$this->value(),
        ]];

        if (isset($this->aggs)) {
            $nested = (array) $this->aggs->toRaw();

            if ($nested !== []) {
                // A nested aggregation named "0" is the only key, so the map is a PHP list.
                $raw[$this->name]['aggs'] = array_is_list($nested) ? (object) $nested : $nested;
            }
        }

        if ($this->meta !== []) {
            $raw[$this->name]['meta'] = object_map($this->meta);
        }

        return $raw;
    }

    public function aggregate(callable $callable)
    {
        $this->aggs ??= new Aggs;

        $callable($this->aggs);

        return $this;
    }

    abstract protected function value();
}
