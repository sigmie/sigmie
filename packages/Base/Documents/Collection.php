<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use Closure;
use Iterator;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Contracts\FromRaw;
use Sigmie\Support\Collection as SigmieCollection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;

class Collection implements DocumentCollectionInterface, FromRaw
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

    public function get(string $index): ?Document
    {
        return $this->collection->get($index);
    }

    public function each(Closure $fn): DocumentCollectionInterface
    {
        $this->collection->each($fn);

        return $this;
    }

    public function offsetExists($offset)
    {
        $this->collection->offsetExists($offset);
    }

    public function offsetGet($offset)
    {
        return $this->collection->offsetGet($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->collection->offsetSet($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->collection->offsetUnset($offset);
    }

    public function count()
    {
        return $this->collection->count();
    }

    public function getIterator()
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

    public function has(string $index): bool
    {
        return $this->collection->hasKey($index);
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

    public function remove(string $index): bool
    {
        $this->collection->remove($index);

        return true;
    }

    public static function fromRaw(array $raw)
    {
        $docs = array_map(fn ($values) => new Document($values['_source'], $values['_id']), $raw);

        return new static($docs);
    }
}
