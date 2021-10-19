<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use ArrayAccess;
use Countable;
use Generator;
use IteratorAggregate;
use Sigmie\Base\Actions\Document as DocumentsActions;
use Sigmie\Base\Actions\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Shared\LazyEach;
use Traversable;

use function Sigmie\Helpers\ensure_doc_collection;


class AliveCollection implements ArrayAccess, Countable, DocumentCollectionInterface, IteratorAggregate
{
    use DocumentsActions, Index, LazyEach, Search;

    public function __construct(
        protected string $name,
        protected string $refresh
    ) {
    }

    public function all(): Generator
    {
        return $this->getIterator();
    }

    public function has(string $index): bool
    {
        return $this->documentExists($this->name, $index);
    }

    public function add(Document $document): self
    {
        $this->createDocument($this->name, $document, $this->refresh);

        return $this;
    }

    public function merge(array|DocumentCollectionInterface $docs,): self
    {
        $docs = ensure_doc_collection($docs);

        $this->upsertDocuments($this->name, $docs, $this->refresh);

        return $this;
    }

    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

    public function clear(): void
    {
        $this->indexAPICall("/{$this->name}/_delete_by_query?refresh={$this->refresh}", 'POST', [
            'query' => ['match_all' => (object)[]]
        ]);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function remove(string $index): bool
    {
        return $this->deleteDocument($this->name, $index, $this->refresh);
    }

    public function get(string $index): ?Document
    {
        return $this->getDocument($this->name, $index);
    }

    public function count(): int
    {
        $res = $this->countAPICall($this->name);

        return $res->json('count');
    }

    public function getIterator(): Traversable
    {
        return $this->indexGenerator();
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): null|Document
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $_id, mixed $doc): void
    {
        $this->add($doc);
    }

    public function offsetUnset(mixed $_id): void
    {
        $this->remove((string) $_id);
    }
}
