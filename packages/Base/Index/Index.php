<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Closure;
use Exception;
use Generator;
use Sigmie\Base\APIs\Count as CountAPI;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Contracts\Name;
use Sigmie\Base\Documents\Actions as DocumentsActions;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Documents\DocumentsCollection;
use Sigmie\Support\Alias\Actions as IndexActions;
use Sigmie\Base\Search\Searchable;
use Sigmie\Support\Collection;
use Sigmie\Support\Index\AliasedIndex;

class Index implements DocumentCollectionInterface, Name
{
    use CountAPI, DocumentsActions, IndexActions, Searchable, API, Actions;

    protected ?int $count;

    protected ?string $size;

    protected int $docsCount;

    protected DocumentCollectionInterface $docs;

    protected array $metadata = [];

    protected bool $prepared;

    protected bool $withIds;

    protected string $prefix;

    protected Settings $settings;

    protected Mappings $mappings;

    public function __construct(
        protected string $identifier,
        protected array $aliases = [],
        Settings $settings = null,
        Mappings $mappings = null
    ) {
        $this->settings = $settings ?: new Settings();
        $this->mappings = $mappings ?: new Mappings();
    }

    public function alias(string $alias): AliasedIndex
    {
        return new AliasedIndex(
            $this->identifier,
            $alias,
            $this->aliases,
            $this->settings,
            $this->mappings
        );
    }

    public function toRaw(): array
    {
        return $this->indexAPICall($this->identifier, 'GET')->json();
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

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function delete()
    {
        return $this->deleteIndex($this->identifier);
    }


    public function setSize(?string $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    /**
     * Get the value of settings
     */
    public function getSettings(): Settings
    {
        return $this->settings;
    }

    public function getMappings(): Mappings
    {
        return $this->mappings;
    }

    public function name(): string
    {
        return $this->identifier;
    }

    public function addDocument(Document $element): self
    {
        $this->createDocument($element, async: false);

        $element->setIndex($this);

        return $this;
    }

    public function addDocuments(array|DocumentCollectionInterface $docs): self
    {
        if (is_array($docs)) {
            $docs = new DocumentsCollection($docs);
        }

        $this->createDocuments($docs, async: false);

        return $this;
    }

    public function addOrUpdateDocuments(array|DocumentCollectionInterface $docs): self
    {
        if (is_array($docs)) {
            $docs = new DocumentsCollection($docs);
        }

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
        if (is_array($docs)) {
            $docs = new DocumentsCollection($docs);
        }

        $this->createDocuments($docs, false);

        return $this;
    }

    public function clear(): void
    {
        $this->deleteIndex($this->identifier);
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

    public function remove(string|array $ids): bool
    {
        if (is_array($ids)) {
            return $this->deleteDocuments($ids);
        }

        return $this->deleteDocument($ids);
    }

    public function contains(string $identifier): bool
    {
        return $this->get($identifier) instanceof Document;
    }

    public function get(string $identifier): ?Document
    {
        return $this->getDocument($identifier);
    }

    public function getIds(): Generator
    {
        foreach ($this->all() as $collection) {
            foreach ($collection as $doc) {
                yield $doc->getId();
            }
        }
    }

    public function set(string $identifier, Document &$document)
    {
        $document->setId($identifier);

        $this->addDocument($document);

        return $this;
    }

    public function toArray(): array
    {
        return iterator_to_array($this->all());
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

    public function forAll(Closure $p)
    {
        foreach ($this->all() as $docsCollection) {
            $docsCollection->map(fn (Document $doc) => $p($doc));
        }

        return $this;
    }

    public function count()
    {
        $res = $this->countAPICall($this->identifier);

        return $res->json('count');
    }

    public function getIterator()
    {
        $perPage = 2;
        $offset = 0;
        $page = 1;

        while ((int) $this->count() > $page * $perPage) {
            yield $this->listDocuments($offset, $perPage);

            $offset = $page * $perPage;
            $page++;
        }

        yield $this->listDocuments($offset, $perPage);
    }

    /**
     * @return Generator<Collection>
     */
    public function all()
    {
        return $this->getIterator();
    }

    public function offsetExists($offset)
    {
        return $this->contains((string) $offset);
    }

    /**
     * @param string $offset
     *
     * @return Document
     */
    public function offsetGet($offset)
    {
        return $this->getDocument((string) $offset);
    }

    /**
     * @param string $identifier
     * @param Document $doc
     *
     * @return void
     * @throws Exception
     */
    public function offsetSet($identifier, $doc)
    {
        if (is_null($identifier)) {
            $this->addDocument($doc);
            return;
        }

        throw new Exception('You can\'t add a documents with an offset.');
    }

    public function offsetUnset($identifier)
    {
        $this->deleteDocument($identifier);
    }

    protected function index(): Index
    {
        return $this;
    }
}
