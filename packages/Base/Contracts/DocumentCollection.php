<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use ArrayAccess;
use Closure;
use Countable;
use IteratorAggregate;
use Sigmie\Base\Documents\Document;

interface DocumentCollection extends ArrayAccess, Countable, IteratorAggregate
{
    public function addDocument(Document &$element): self;

    public function addDocuments(array|DocumentCollection $documentCollection): self;

    public function clear(): void;

    public function isEmpty(): bool;

    public function isNotEmpty(): bool;

    public function toArray(): array;

    public function remove(string $identifier);

    public function contains(string $identifier): bool;

    public function get(string $identifier): ?Document;

    public function first(): ?Document;

    public function last(): ?Document;

    public function forAll(Closure $p);
}
