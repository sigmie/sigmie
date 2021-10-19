<?php

declare(strict_types=1);

namespace Sigmie\Support;

use ArrayIterator;
use Closure;
use function Sigmie\Helpers\ensure_collection;

use Sigmie\Support\Contracts\Collection as CollectionInterface;
use Traversable;

class Collection implements CollectionInterface
{
    public function __construct(protected array $elements = [])
    {
    }

    public function deepen(int|float $depth = INF): static
    {
        $result = [];

        foreach ($this->elements as $key => $item) {
            $result[] = [$key => $item];
        }

        return new static($result);
    }

    public function toArray(): array
    {
        return $this->elements;
    }

    public function merge(CollectionInterface|array $values): static
    {
        $values = ensure_collection($values);
        $result = array_merge($this->toArray(), $values->toArray());

        return new static($result);
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     */
    public function flatten(int|float $depth = INF): static
    {
        $result = [];

        foreach ($this->elements as $item) {
            $item = $item instanceof Collection ? $item->toArray() : $item;

            if (!is_array($item)) {
                $result[] = $item;
            } else {
                $values = $depth === 1
                    ? array_values($item)
                    : (new static($item))->flatten($depth - 1)->toArray();

                foreach ($values as $value) {
                    $result[] = $value;
                }
            }
        }

        return new static($result);
    }

    /**
     * Flatten a multi-dimensional array by keeping the keys.
     */
    public function flattenWithKeys(int $depth = 1): static
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
    public function mapWithKeys(callable $callback): static
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

    public function mapToDictionary(callable $callback): static
    {
        $dictionary = [];

        foreach ($this->toArray() as $key => $item) {
            $pair = $callback($item, $key);

            $key = key($pair);

            $value = reset($pair);

            $dictionary[$key] = $value;
        }

        return new static($dictionary);
    }

    public function first(): mixed
    {
        return (reset($this->elements)) ?: null;
    }

    public function last(): mixed
    {
        return (end($this->elements)) ?: null;
    }

    public function key(): mixed
    {
        return key($this->elements);
    }

    public function next(): mixed
    {
        return next($this->elements);
    }

    public function current(): mixed
    {
        return current($this->elements);
    }

    public function remove(string|int $key): static
    {
        if (!isset($this->elements[$key]) && !array_key_exists($key, $this->elements)) {
            return new static($this->elements);
        }

        unset($this->elements[$key]);

        return new static($this->elements);
    }

    public function removeElement(mixed $element): static
    {
        $key = array_search($element, $this->elements, true);

        if ($key === false) {
            return new static($this->elements);
        }

        unset($this->elements[$key]);

        return new static($this->elements);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->hasKey($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->elements[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->elements[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->elements[$offset]);
    }

    public function hasKey(string|int $key): bool
    {
        return isset($this->elements[$key]) || array_key_exists($key, $this->elements);
    }

    public function contains(mixed $element): bool
    {
        return in_array($element, $this->elements, true);
    }

    public function exists(Closure $p): bool
    {
        foreach ($this->elements as $key => $element) {
            if ($p($key, $element)) {
                return true;
            }
        }

        return false;
    }

    public function indexOf($element): int|string
    {
        return array_search($element, $this->elements, true);
    }

    public function get(string|int $key): mixed
    {
        return $this->elements[$key] ?? null;
    }

    public function keys(): array
    {
        return array_keys($this->elements);
    }

    public function values(): array
    {
        return array_values($this->elements);
    }

    public function count(): int
    {
        return count($this->elements);
    }

    public function set(string|int $key, mixed $value): static
    {
        $this->elements[$key] = $value;

        return new static($this->elements);
    }

    public function add(mixed $element): static
    {
        $this->elements[] = $element;

        return new static($this->elements);
    }

    public function isEmpty(): bool
    {
        return empty($this->elements);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->elements);
    }

    public function map(Closure $func): static
    {
        return new static(array_map($func, $this->elements));
    }

    public function filter(Closure $p): static
    {
        return new static(array_filter($this->elements, $p, ARRAY_FILTER_USE_BOTH));
    }

    public function each(Closure $p): static
    {
        foreach ($this->elements as $key => $element) {
            $p($element, $key);
        }

        return new static($this->elements);
    }

    public function clear(): static
    {
        $this->elements = [];

        return new static($this->elements);
    }

    public function slice(int $offset, int|null $length = null): static
    {
        return new static(array_slice($this->elements, $offset, $length, true));
    }
}
