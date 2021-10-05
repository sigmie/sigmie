<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use ArrayAccess;
use Countable;
use Exception;
use Generator;
use IteratorAggregate;
use Sigmie\Base\APIs\Analyze;
use Sigmie\Base\APIs\Count as CountAPI;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Contracts\MappingsInterface as MappingsInterface;
use Sigmie\Base\Documents\Actions as DocumentsActions;
use Sigmie\Base\Documents\Collection as DocumentsCollection;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Search\Searchable;
use Sigmie\Base\Shared\LazyEach;
use function Sigmie\Helpers\ensure_doc_collection;
use Sigmie\Support\Collection;

use Sigmie\Support\Index\AliasedIndex;

class CollectedIndex extends AbstractIndex implements ArrayAccess, Countable, IteratorAggregate
{
    use DocumentsActions, IndexActions, LazyEach, Search;

    public function all(): Generator
    {
        return $this->getIterator();
    }

    public function has(string $_id): bool
    {
        return $this->documentExists($this->name, $_id);
    }

    public function add(Document $document, string $refresh = 'false'): self
    {
        $this->createDocument($this->name, $document, $refresh);

        $document->_index = $this;

        return $this;
    }

    public function merge(array|DocumentCollectionInterface $docs, string $refresh = 'false'): self
    {
        $docs = ensure_doc_collection($docs);

        $this->upsertDocuments($this->name, $docs, $refresh);

        return $this;
    }

    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

    public function clear(string $refresh): void
    {
        $this->indexAPICall("/{$this->name}/_delete_by_query?refresh={$refresh}", 'POST', [
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

    public function remove(string $_id, string $refresh = 'false'): bool
    {
        return $this->deleteDocument($this->name, $_id, $refresh);
    }

    public function get(string $_id): ?Document
    {
        return $this->getDocument($this->name, $_id);
    }

    public function count(): int
    {
        $res = $this->countAPICall($this->name);

        return $res->json('count');
    }

    public function getIterator()
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
