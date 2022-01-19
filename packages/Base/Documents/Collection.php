<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use Closure;
use Countable;
use Iterator;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Contracts\FromRaw;
use Sigmie\Support\Collection as SigmieCollection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;
use Traversable;

class Collection implements Countable, DocumentCollectionInterface, FromRaw
{
    protected CollectionInterface $collection;

    public function __construct(array $documents = [])
    {
        $this->collection = new SigmieCollection($documents);
    }

    public function all(): Iterator
    {
        return $this->collection->getIterator();
    }

    public function get(string $_id): ?Document
    {
        return $this->collection->get($_id);
    }

    public function each(Closure $fn): DocumentCollectionInterface
    {
        $this->collection->each($fn);

        return $this;
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

    public function count(): int
    {
        return $this->collection->count();
    }

    public function getIterator(): Traversable
    {
        return $this->collection->getIterator();
    }

    public function add(Document $element): self
    {
        $this->collection->add($element);

        return $this;
    }

    public function merge(array|DocumentCollectionInterface $documents): self
    {
        if (is_array($documents)) {
            $documents = new Collection($documents);
        }

        $this->collection = $this->collection->merge($documents->toArray());

        return $this;
    }

    public function has(string $_id): bool
    {
        return $this->collection->hasKey($_id);
    }

    public function toArray(): array
    {
        return $this->collection->map(fn (Document $doc) => $doc->toArray())->toArray();
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

    public static function fromRaw(array $raw)
    {
        $docs = array_map(fn ($values) => Document::fromRaw($values), $raw);

        return new static($docs);
    }
}
