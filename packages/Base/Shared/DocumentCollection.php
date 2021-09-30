<?php


declare(strict_types=1);

namespace Sigmie\Base\Shared;

use Exception;
use Generator;
use Iterator;
use Sigmie\Base\APIs\Count;
use Sigmie\Base\Documents\Actions;
use Sigmie\Base\Documents\Document;

trait DocumentCollection
{
    use LazyEach;

    public function getIterator(): Iterator
    {
        return $this->indexIterator();
    }

    public function all(): Generator
    {
        return $this->getIterator();
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->contains((string) $offset);
    }

    public function offsetGet(mixed $offset): null|Document
    {
        return $this->getDocument((string) $offset);
    }

    public function offsetSet(mixed $identifier, mixed $doc): void
    {
        if (is_null($identifier)) {
            $this->addDocument($doc);
            return;
        }

        throw new Exception('You can\'t add a documents with an offset.');
    }

    public function offsetUnset(mixed $identifier): void
    {
        $this->deleteDocument($identifier);
    }

    public function count(): int
    {
        $res = $this->countAPICall($this->name);

        return $res->json('count');
    }

    public function toArray(): array
    {
        return iterator_to_array($this->getIterator(), false);
    }

    public function first(): Document
    {
        return $this->listDocuments(0, 1)->first();
    }

    public function last(): Document
    {
        $all = $this->count();
        $last = $this->listDocuments($all - 1, 1);

        return $last->first();
    }

    public function set(string $_id, Document $document): self
    {
        $document->_id = $_id;

        $this->addDocument($document);

        return $this;
    }

    public function contains(string $_id): bool
    {
        return $this->get($_id) instanceof Document;
    }

    public function get(string $identifier): ?Document
    {
        return $this->getDocument($identifier);
    }

    public function remove(string|array $ids): void
    {
        if (is_array($ids)) {
            $this->deleteDocuments($ids);
            return;
        }

        $this->deleteDocument($ids);
    }

    public function clear(): void
    {
        $this->deleteIndex($this->name);
        $this->createIndex($this);
    }

    public function isEmpty(): bool
    {
        return (int) $this->count() === 0;
    }

    public function isNotEmpty(): bool
    {
        return (int) $this->count() > 0;
    }
}
