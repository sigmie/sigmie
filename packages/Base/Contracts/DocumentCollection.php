<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use ArrayAccess;
use Closure;
use Countable;
use Generator;
use IteratorAggregate;
use Sigmie\Base\Documents\Document;

interface DocumentCollection extends ArrayAccess, Countable, IteratorAggregate
{
    public function add(Document $document): self;

    public function merge(array|DocumentCollection $documents): self;

    public function clear(): void;

    public function isEmpty(): bool;

    public function isNotEmpty(): bool;

    public function toArray(): array;

    public function remove(string $_id): bool;

    public function all(): Generator;

    public function has(string $_id): bool;

    public function get(string $_id): ?Document;

    public function each(Closure $fn): self;
}
