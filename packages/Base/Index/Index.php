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
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Search\Searchable;
use Sigmie\Base\Shared\LazyEach;
use function Sigmie\Helpers\ensure_doc_collection;
use Sigmie\Support\Collection;

use Sigmie\Support\Index\AliasedIndex;

/**
 * @property-read Mappings $mappings;
 * @property-write Settings $settings;
 */
abstract class Index
{
    use API, IndexActions, DocumentsActions;

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

    public function paginate(int $perPage, int $currentPage = 1): PaginatedIndex
    {
        return new PaginatedIndex(
            $this->name,
            $this->aliases,
        );
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

    public function collect(): CollectedIndex
    {
        return new CollectedIndex(
            $this->name,
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
        $this->createDocument($this->name, $document, async: false);

        $document->_index = $this;

        return $this;
    }

    public function addDocuments(array|DocumentCollectionInterface $docs): self
    {
        $docs = ensure_doc_collection($docs);

        $this->createDocuments($this->name, $docs, async: false);

        return $this;
    }

    public function addOrUpdateDocuments(array|DocumentCollectionInterface $docs): self
    {
        $docs = ensure_doc_collection($docs);

        $this->upsertDocuments($this->name, $docs);

        return $this;
    }

    public function addAsyncDocument(Document $element): self
    {
        $this->createDocument($this->name, $element, async: true);

        return $this;
    }

    public function addAsyncDocuments(array|DocumentCollectionInterface $docs): self
    {
        $docs = ensure_doc_collection($docs);

        $this->createDocuments($this->name, $docs, false);

        return $this;
    }
}
