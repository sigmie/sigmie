<?php

declare(strict_types=1);

namespace Sigmie\Query\Shared;

use function Sigmie\Functions\keyed;

trait OrdersBuckets
{
    protected array $order = [];

    public function order(string $subaggregation, string $direction): static
    {
        $this->order = ['order' => keyed($subaggregation, $direction)];

        return $this;
    }

    /** @param array<string, string> $criteria */
    public function orderBy(array $criteria): static
    {
        $order = [];
        foreach ($criteria as $name => $direction) {
            $order[] = keyed((string) $name, $direction);
        }
        $this->order = ['order' => $order];

        return $this;
    }
}
