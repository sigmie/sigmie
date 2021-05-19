<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Closure;
use Exception;
use Generator;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\APIs\Calls\Count as CountAPI;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Documents\Actions as DocumentsActions;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Documents\DocumentsCollection;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Mappings\Properties;
use Sigmie\Base\Search\Searchable;
use Sigmie\Support\Collection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Index implements DocumentCollectionInterface
{
    use CountAPI, DocumentsActions, IndexActions, Searchable, API, AliasActions;

    protected EventDispatcherInterface $events;

    protected function events(): EventDispatcherInterface
    {
        return $this->events;
    }

    protected string $name;

    protected ?int $count;

    protected ?string $size;

    protected array $aliases = [];

    protected int $docsCount;

    protected Settings $settings;

    protected Mappings $mappings;

    protected DocumentCollectionInterface $docs;

    protected array $metadata = [];

    protected bool $prepared;

    protected bool $withIds;

    public function __construct(
        string $name,
        Settings $settings = null,
        Mappings $mappings = null
    ) {
        if ($settings === null) {
            $settings = new Settings();
        }

        if ($mappings === null) {
            //TODO make mappings required parameter
            $mappings = new Mappings(
                new Analyzer('demo', new WordBoundaries(100), []),
                new Properties()
            );
        }

        $this->name = $name;
        $this->settings = $settings;
        $this->mappings = $mappings;
    }

    public function delete()
    {
        return $this->deleteIndex($this->name);
    }

    public function setAlias(string $alias): self
    {
        $this->aliases[] = $alias;

        $this->createAlias($this->name, $alias);

        return $this;
    }

    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function removeAlias(string $alias)
    {
        return $this->deleteAlias($this->name, $alias);
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

    /**
     * Get the value of name
     */
    public function getName()
    {
        return $this->name;
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
        $res = $this->countAPICall($this->name);

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
