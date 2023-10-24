<?php

declare(strict_types=1);

namespace Sigmie\Document\Contracts;

use ArrayAccess;
use Closure;
use Countable;
use IteratorAggregate;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Document;
use Traversable;

interface DocumentCollection extends ArrayAccess, Countable, IteratorAggregate
{
    public function add(Document $document): Document;

    public function replace(Document $document): Document;

    public function merge(array $documents): AliveCollection;

    public function clear(): void;

    public function isEmpty(): bool;

    public function isNotEmpty(): bool;

    public function toArray(): array;

    public function remove(string $_id): bool;

    public function all(): Traversable;

    public function has(string $_id): bool;

    public function get(string $_id): ?Document;

    public function each(Closure $fn): self;
}
