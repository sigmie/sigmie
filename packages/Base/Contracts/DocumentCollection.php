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
    public function addDocument(Document $document): self;

    public function addDocuments(array|DocumentCollection $documents): self;

    public function clear(): void;

    public function isEmpty(): bool;

    public function isNotEmpty(): bool;

    public function toArray(): array;

    public function remove(string $_id): void;

    public function contains(string $_id): bool;

    public function get(string $_id): ?Document;

    public function first(): ?Document;

    public function last(): ?Document;

    public function each(Closure $fn): self;

}
