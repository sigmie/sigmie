<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Exception;
use Generator;
use Sigmie\Base\APIs\Analyze;
use Sigmie\Base\APIs\Count as CountAPI;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Documents\Actions as DocumentsActions;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Search\Searchable;
use Sigmie\Base\Shared\LazyEach;
use function Sigmie\Helpers\ensure_doc_collection;
use Sigmie\Support\Alias\Actions as IndexActions;
use Sigmie\Support\Collection;

use Sigmie\Support\Index\AliasedIndex;

class Index implements DocumentCollectionInterface
{
    use CountAPI, DocumentsActions, IndexActions, Searchable, API, Actions, Analyze, LazyEach;

    protected Settings $settings;

    protected MappingsInterface $mappings;

    public function __construct(
        protected string $name,
        protected array $aliases = [],
        Settings $settings = null,
        MappingsInterface $mappings = null
    ) {
        $this->settings = $settings ?: new Settings();
        $this->mappings = $mappings ?: new Mappings();
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name === 'settings' && isset($this->settings)) {
            $class = $this::class;
            user_error("Error: Cannot modify readonly property {$class}::{$name}");
        }

        if ($name === 'mappings' && isset($this->mappings)) {
            $class = $this::class;
            user_error("Error: Cannot modify readonly property {$class}::{$name}");
        }

        if ($name === 'name' && isset($this->name)) {
            $class = $this::class;
            user_error("Error: Cannot modify readonly property {$class}::{$name}");
        }
    }

    public function __get(string $attribute): mixed
    {
        return $this->$attribute;
    }

    public function alias(string $alias): AliasedIndex
    {
        return new AliasedIndex(
            $this->name,
            $alias,
            $this->aliases,
            $this->settings,
            $this->mappings
        );
    }

    public function toRaw(): array
    {
        return [
            'settings' => $this->settings->toRaw(),
            'mappings' => $this->mappings->toRaw(),
        ];
    }

    public static function fromRaw(string $name, array $raw): static
    {
        $settings = Settings::fromRaw($raw);
        $analyzers = $settings->analysis()->analyzers();
        $mappings = Mappings::fromRaw($raw['mappings'], $analyzers);
        $aliases = array_keys($raw['aliases']);

        $index = new static($name, $aliases, $settings, $mappings);

        return $index;
    }

    public function delete(): bool
    {
        return $this->deleteIndex($this->name);
    }

    public function addDocument(Document $document): self
    {
        $this->createDocument($document, async: false);

        $document->_index = $this;

        return $this;
    }

    public function addDocuments(array|DocumentCollectionInterface $docs): self
    {
        $docs = ensure_doc_collection($docs);

        $this->createDocuments($docs, async: false);

        return $this;
    }

    public function addOrUpdateDocuments(array|DocumentCollectionInterface $docs): self
    {
        $docs = ensure_doc_collection($docs);

        $this->upsertDocuments($docs);

        return $this;
    }

    public function addAsyncDocument(Document $element): self
    {
        $this->createDocument($element, async: true);

        return $this;
    }

    public function addAsyncDocuments(array|DocumentCollectionInterface $docs): self
    {
        $docs = ensure_doc_collection($docs);

        $this->createDocuments($docs, false);

        return $this;
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

    public function remove(string|array $ids): void
    {
        if (is_array($ids)) {
            $this->deleteDocuments($ids);
            return;
        }

        $this->deleteDocument($ids);
    }

    public function contains(string $_id): bool
    {
        return $this->get($_id) instanceof Document;
    }

    public function get(string $identifier): ?Document
    {
        return $this->getDocument($identifier);
    }

    public function set(string $_id, Document $document): self
    {
        $document->_id = $_id;

        $this->addDocument($document);

        return $this;
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

    public function count()
    {
        $res = $this->countAPICall($this->name);

        return $res->json('count');
    }

    public function getIterator()
    {
        $offset = 0;
        $page = 1;

        while ((int) $this->count() > $page * $this->chunk) {
            yield $this->listDocuments($offset, $this->chunk);

            $offset = $page * $this->chunk;
            $page++;
        }

        yield $this->listDocuments($offset, $this->chunk);
    }

    /**
     * @return Generator<Collection>
     */
    public function all()
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

    private function index(): Index
    {
        return $this;
    }
}
