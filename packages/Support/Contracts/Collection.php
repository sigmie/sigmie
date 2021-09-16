<?php

declare(strict_types=1);

namespace Sigmie\Support\Contracts;

use ArrayAccess;
use Closure;
use Countable;
use IteratorAggregate;

interface Collection extends ArrayAccess, Countable, IteratorAggregate
{
    public function deepen(int|float $depth = INF): static;

    public function flatten(int|float $depth = INF): static;

    public function flattenWithKeys(int $depth = 1): static;

    public function mapWithKeys(callable $callback): static;

    public function mapToDictionary(callable $callback): static;

    public function toArray(): array;

    public function merge(Collection|array $values): static;

    public function slice(int $offset, int|null $length = null): static;

    public function clear(): static;

    public function each(Closure $p): static;

    public function filter(Closure $p): static;

    public function map(Closure $func): static;

    public function isEmpty(): bool;

    public function add(mixed $element): static;

    public function count(): int;

    public function set(string|int $key, mixed $value): static;

    public function values(): array;

    public function keys(): array;

    public function get(string|int $key): mixed;

    public function indexOf(mixed $element): int|string;

    public function exists(Closure $p): bool;

    public function contains(mixed $element): bool;

    public function hasKey(string|int $key): bool;

    public function removeElement(mixed $element): static;

    public function remove(string|int $key): static;

    public function current(): mixed;

    public function next(): mixed;

    public function key(): mixed;

    public function last(): mixed;

    public function first(): mixed;
}
