<?php

declare(strict_types=1);

namespace Sigmie\Support;

use Closure;
use Doctrine\Common\Collections\ArrayCollection as DoctrineCollection; use Sigmie\Support\Contracts\Collection as CollectionInterface;

class Collection extends DoctrineCollection implements CollectionInterface
{
    /**
     * Flatten a multi-dimensional array into a single level.
     */
    public function flatten($depth = INF): self
    {
        $result = [];
        foreach ($this->toArray() as $item) {
            $item = $item instanceof Collection ? $item->toArray() : $item;

            if (!is_array($item)) {
                $result[] = $item;
            } else {
                $values = $depth === 1
                    ? array_values($item)
                    : (new static($item))->flatten($depth - 1);

                foreach ($values as $value) {
                    $result[] = $value;
                }
            }
        }

        return new static($result);
    }

    public function forAll(Closure $p)
    {
        parent::forAll(function (...$args) use ($p) {
            $p(...$args);

            return true;
        });
    }

    /**
     * Flatten a multi-dimensional array by keeping the keys.
     *
     * @return Collection
     */
    public function flattenWithKeys($depth = 1): self
    {
        $result = [];

        foreach ($this->toArray() as $key => $item) {
            $item = $item instanceof CollectionInterface ? $item->toArray() : $item;

            if (!is_array($item)) {
                $result[$key] = $item;
            } else {
                if ($depth === 0) {
                    $values = [$key => $item];
                } else {
                    $values = (new static($item))->flattenWithKeys($depth - 1)->toArray();
                }

                foreach ($values as $key => $value) {
                    $result[$key] = $value;
                }
            }
        }

        return new static($result);
    }

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     */
    public function mapWithKeys(callable $callback): self
    {
        $result = [];

        foreach ($this->toArray() as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return new static($result);
    }

    public function last()
    {
        $last = parent::last();

        if ($last) {
            return $last;
        }

        return null;
    }

    public function first()
    {
        $first = parent::first();

        if ($first) {
            return $first;
        }

        return null;
    }
}
