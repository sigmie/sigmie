<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use Closure;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Support\Contracts\Collection as CollectionInterface;

trait Collection
{
    protected CollectionInterface $collection;

    public function addDocument(Document $element): self
    {
        $this->collection->add($element);

        return $this;
    }

    public function addDocuments(array|DocumentCollectionInterface $documents): self
    {
        if (is_array($documents)) {
            $documents = new DocumentCollection($documents);
        }

        foreach ($documents as $document) {
            $this->addDocument($document);
        }

        return $this;
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

    public function remove(string $_id): void
    {
        $this->collection->remove($_id);
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

    public function first(): ?Document
    {
        return $this->collection->first();
    }

    public function last(): ?Document
    {
        return $this->collection->last();
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
