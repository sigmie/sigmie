<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use Closure;
use Generator;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Support\Contracts\Collection as CollectionInterface;

trait Collection
{
    protected CollectionInterface $collection;

    public function add(Document $element): self
    {
        $this->collection->add($element);

        return $this;
    }

    public function merge(array|DocumentCollectionInterface $documents): self
    {
        if (is_array($documents)) {
            $documents = new DocumentCollection($documents);
        }

        $this->collection = $this->collection->merge($documents->toArray());

        return $this;
    }

    public function has(string $_id): bool
    {
        return $this->collection->hasKey($_id);
    }

    public function all(): Generator
    {
        return $this->collection->toArray();
    }

    public function toArray(): array
    {
        return $this->collection->toArray();
    }

    public function clear(): void
    {
        $this->collection->clear();
    }

    public function isEmpty(): bool
    {
        return $this->collection->isEmpty();
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function remove(string $_id): bool
    {
        $this->collection->remove($_id);

        return true;
    }

    public function contains(string $_id): bool
    {
        $isEmpty = $this->collection
            ->filter(
                fn (Document $document) => $document->_id === $_id
            )
            ->isEmpty();

        return !$isEmpty;
    }

    public function get(string $_id): ?Document
    {
        $doc = $this->collection
            ->filter(
                fn (Document $document) => $document->_id === $_id
            )->first();

        if ($doc instanceof Document) {
            return $doc;
        }

        return null;
    }

    public function each(Closure $p): self
    {
        $this->collection->each($p);

        return $this;
    }

    public function count(): int
    {
        return $this->collection->count();
    }

    public function getIterator()
    {
        return $this->collection->getIterator();
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->collection->offsetExists($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->collection->offsetGet($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->collection->offsetSet($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->collection->offsetUnset($offset);
    }
}
