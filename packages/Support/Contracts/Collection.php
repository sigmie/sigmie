<?php

declare(strict_types=1);

namespace Sigmie\Support\Contracts;

use Closure;
use Doctrine\Common\Collections\Collection as DoctrineCollection;

interface Collection
{
    public function flatten(int $depth = INF): self;

    public function flattenWithKeys(int $depth = 1): self;

    public function mapWithKeys(callable $callback): self;

    public function mapToDictionary(callable $callback): self;

    public function sortByKeys(): self;

    public function merge(Collection|array $values): self;

    public function slice(int $offset, int|null $length = null): static;

    public function clear(): static;

    public function each(Closure $p): static;

    public function filter(Closure $p): static;

    public function map(Closure $func): static;

    public function isEmpty(): bool;

    public function add(mixed $element): static;

    public function count(): int;

    public function set($key, $value): static;

    public function values(): array;

    public function keys(): array;

    public function get($key): mixed;

    public function indexOf($element): int|string;

    public function exists(Closure $p): bool;

    public function contains(mixed $element): bool;

    public function containsKey(string|int $key): bool;

    public function removeElement(mixed $element): static;

    public function current(): mixed;

    public function next(): mixed;

    public function key(): mixed;

    public function last(): mixed;

    public function first(): mixed;
}
