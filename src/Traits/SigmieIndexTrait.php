<?php

declare(strict_types=1);

namespace Sigmie\Traits;

use InvalidArgumentException;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Document;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Index;
use Sigmie\Index\NewIndex;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\NewQuery;
use Sigmie\Search\NewMultiSearch;
use Sigmie\Search\NewSearch;
use Sigmie\Search\RawQuery;
use Sigmie\Sigmie;

trait SigmieIndexTrait
{
    protected Sigmie $sigmie;

    protected string $indexName;

    protected NewProperties $blueprint;

    /**
     * Get the index name
     */
    public function name(): string
    {
        return $this->indexName;
    }

    /**
     * Get the properties blueprint for this index
     */
    public function properties(): NewProperties
    {
        return $this->blueprint;
    }

    /**
     * Create the index in Elasticsearch
     */
    public function create(): AliasedIndex
    {
        return $this->buildIndex()->create();
    }

    /**
     * Delete the index from Elasticsearch
     */
    public function delete(): void
    {
        $this->sigmie->deleteIfExists($this->indexName);
    }

    /**
     * Update the index with a new configuration
     */
    public function update(callable $callback): AliasedIndex
    {
        $existingIndex = $this->index();

        // @codeCoverageIgnoreStart
        if ($existingIndex instanceof AliasedIndex) {
            return $existingIndex->update(fn (NewIndex $builder) => $callback($builder->properties($this->blueprint)));
        }

        // @codeCoverageIgnoreEnd

        $newIndex = $this->buildIndex();
        $newIndex = $callback($newIndex);

        return $newIndex->create();
    }

    /**
     * Convert data to Document instances
     */
    public function toDocuments(array $data): array
    {
        $documents = [];

        foreach ($data as $item) {
            if ($item instanceof Document) {
                $documents[] = $item;
            } elseif (is_array($item)) {
                $documents[] = new Document($item);
                // @codeCoverageIgnoreStart
            } else {
                throw new InvalidArgumentException('Data must be an array or Document instance');
            }

            // @codeCoverageIgnoreEnd
        }

        return $documents;
    }

    /**
     * Get the underlying index instance
     */
    public function index(): null|AliasedIndex|Index
    {
        return $this->sigmie->index($this->indexName);
    }

    /**
     * Build a new index with properties
     */
    protected function buildIndex(): NewIndex
    {
        return $this->sigmie->newIndex($this->indexName)
            ->properties($this->blueprint);
    }

    /**
     * Create a new search instance with properties pre-configured
     */
    public function newSearch(): NewSearch
    {
        return $this->sigmie->newSearch($this->indexName)
            ->properties($this->blueprint);
    }

    /**
     * Create a new multi-search instance
     */
    public function newMultiSearch(): object
    {
        $multiSearch = $this->sigmie->newMultiSearch();

        return new class($multiSearch, $this->blueprint, $this->indexName)
        {
            public function __construct(private NewMultiSearch $multiSearch, private NewProperties $properties, private string $defaultIndex) {}

            public function newSearch(?string $indexName = null): NewSearch
            {
                $index = $indexName ?? $this->defaultIndex;

                $search = $this->multiSearch->newSearch($index);

                return $index === $this->defaultIndex ? $search->properties($this->properties) : $search;
            }

            public function newQuery(?string $indexName = null): NewQuery
            {
                $index = $indexName ?? $this->defaultIndex;

                $query = $this->multiSearch->newQuery($index);

                return $index === $this->defaultIndex ? $query->properties($this->properties) : $query;
            }

            public function raw(string $indexName, array $query): RawQuery
            {
                return $this->multiSearch->raw($indexName, $query);
            }

            public function get(): array
            {
                return $this->multiSearch->get();
            }
        };
    }

    /**
     * Create a new query instance
     */
    public function newQuery(): NewQuery
    {
        return $this->sigmie->newQuery($this->indexName)->properties($this->blueprint);
    }

    /**
     * Get a collection for this index
     */
    public function collect(bool $refresh = false): AliveCollection
    {
        $collection = $this->sigmie->collect($this->indexName, $refresh);
        $collection->properties($this->blueprint);

        return $collection;
    }

    /**
     * Refresh the index
     */
    public function refresh(): void
    {
        $this->sigmie->refresh($this->indexName);
    }

    /**
     * Check if the index exists
     */
    public function exists(): bool
    {
        return $this->sigmie->indexAPICall($this->indexName.'?allow_no_indices=false', 'HEAD')->code() === 200;
    }
}
