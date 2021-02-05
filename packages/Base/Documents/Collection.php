<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use Closure;
use Sigmie\Base\Contracts\DocumentCollection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;

trait Collection
{
    protected CollectionInterface $collection;

    public function addDocument(Document $element): self
    {
        $this->collection->add($element);

        return $this;
    }

    public function addDocuments(array|DocumentCollection $documents): self
    {
        if (is_array($documents))
        {
            $documents = new DocumentsCollection($documents);
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

    public function remove(string $identifier): bool
    {
        return $this->collection->remove($identifier);
    }

    public function contains(string $id): bool
    {
        $isEmpty = $this->collection
            ->filter(
                fn (Document $document) => $document->getId() === $id
            )
            ->isEmpty();

        return !$isEmpty;
    }

    public function get(string $id): ?Document
    {
        $doc = $this->collection
            ->filter(
                fn (Document $document) => $document->getId() === $id
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

    public function forAll(Closure $p): self
    {
        $this->collection->forAll($p);

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

    public function offsetExists($offset)
    {
        return $this->collection->offsetExists($offset);
    }

    public function offsetGet($offset)
    {
        return $this->collection->offsetGet($offset);
    }

    public function offsetSet($offset, $value)
    {
        return $this->collection->offsetSet($offset, $value);
    }

    public function offsetUnset($offset)
    {
        return $this->collection->offsetUnset($offset);
    }
}
