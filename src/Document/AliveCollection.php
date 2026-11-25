<?php

declare(strict_types=1);

namespace Sigmie\Document;

use ArrayAccess;
use Countable;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Document\Actions as DocumentActions;
use Sigmie\Document\Contracts\CollectionHook;
use Sigmie\Document\Contracts\DocumentCollection;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Index\Shared\Mappings;
use Sigmie\Mappings\Properties;
use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Semantic\DocumentProcessor;
use Sigmie\Shared\Collection;
use Sigmie\Shared\UsesApis;
use Sigmie\Sigmie;
use Traversable;

class AliveCollection implements ArrayAccess, Countable, DocumentCollection
{
    use DocumentActions;
    use IndexActions;
    use LazyEach;
    use Mappings;
    use Search;
    use UsesApis;

    /** @var array<int, CollectionHook> */
    protected array $hooks = [];

    protected bool $hooksEnabled = true;

    protected ?array $only = null;

    protected ?array $except = null;

    /** @var list<QueryClause> */
    protected array $filterQueries = [];

    protected bool $populateEmbeddings = true;

    public function __construct(
        protected string $name,
        ElasticsearchConnection $connection,
        protected string $refresh = 'false',
        bool $requireAlias = false,
    ) {
        $this->setElasticsearchConnection($connection);

        $this->requireAlias = $requireAlias;
        $this->properties = new Properties;
    }

    protected function sigmie(): Sigmie
    {
        return new Sigmie($this->elasticsearchConnection);
    }

    /**
     * @param  array<int, CollectionHook>  $hooks
     */
    public function hooks(array $hooks): static
    {
        $this->hooks = $hooks;

        return $this;
    }

    public function withoutHooks(): static
    {
        $this->hooksEnabled = false;

        return $this;
    }

    public function populateEmbeddings(bool $value = true): static
    {
        $this->populateEmbeddings = $value;

        return $this;
    }

    public function getMany(array $ids): array
    {
        return $this->retrieveDocuments($this->name, $ids)->toArray();
    }

    public function refresh(): static
    {
        $this->refreshIndex($this->name);

        return $this;
    }

    public function replace(Document $document): Document
    {
        return $this->store($document, replace: true);
    }

    public function all(): Traversable
    {
        return $this->getIterator();
    }

    public function has(string $_id): bool
    {
        return $this->documentExists($this->name, $_id);
    }

    public function random(int $size = 10): Collection
    {
        if ($size === 0) {
            return new Collection;
        }

        $payload = [
            'from' => 0,
            'size' => $size,
            'query' => [
                'function_score' => [
                    'query' => $this->filterQueries === []
                        ? ['match_all' => (object) []]
                        : ['bool' => ['filter' => array_map(fn (QueryClause $query): array => $query->toRaw(), $this->filterQueries)]],
                    'random_score' => (object) [],
                    'boost_mode' => 'replace',
                ],
            ],
        ];

        if ($this->only || $this->except) {
            $payload['_source'] = array_filter(['includes' => $this->only, 'excludes' => $this->except]);
        }

        $response = $this->searchAPICall($this->name, $payload);

        $collection = new Collection;

        $values = $response->json('hits')['hits'];

        foreach ($values as $data) {
            $collection->add(Document::fromRaw($data));
        }

        return $collection;
    }

    public function take(int $limit): array
    {
        if ($limit === 0) {
            return [];
        }

        $reverse = $limit < 0;
        $count = $limit === PHP_INT_MIN ? PHP_INT_MAX : abs($limit);
        $documents = [];
        foreach ($this->indexGenerator($reverse, min($count, $this->chunk)) as $hit) {
            $documents[] = Document::fromRaw([
                '_id' => $hit->_id ?? null,
                '_index' => $hit->_index ?? null,
                '_source' => $hit->_source,
            ]);
            if (count($documents) >= $count) {
                break;
            }
        }

        return $reverse ? array_reverse($documents) : $documents;
    }

    public function add(Document $document): Document
    {
        return $this->store($document, replace: false);
    }

    private function store(Document $document, bool $replace): Document
    {
        $activeHooks = $this->activeHooks();

        foreach ($activeHooks as $hook) {
            $hook->beforeBatch($this->name, $this->sigmie(), $this->properties, $this->apis);
        }

        $document = $this->processDocument($document, $activeHooks);

        $this->writeDocument($this->name, $document, $this->refresh, $replace, $this->properties);

        foreach ($activeHooks as $hook) {
            $hook->afterBatch([$document], $this->name, $this->sigmie(), $this->properties, $this->apis);
        }

        return $document;
    }

    public function merge(array $documents): AliveCollection
    {
        if ($documents === []) {
            return $this;
        }

        $activeHooks = $this->activeHooks();

        foreach ($activeHooks as $hook) {
            $hook->beforeBatch($this->name, $this->sigmie(), $this->properties, $this->apis);
        }

        $processor = new DocumentProcessor($this->properties, $this->elasticsearchConnection->driver()->engine());
        $processor->apis($this->apis);

        $needsCurrent = [];
        $documents = array_values($documents);
        foreach ($documents as $position => $document) {
            $processor->validateFields($processor->formatDateTimeFields($document));
            if (isset($document->_id) && $processor->patchNeedsCurrentSource($document, $this->populateEmbeddings)) {
                $needsCurrent[$position] = $document->_id;
            }
        }

        $contextIds = array_fill_keys(array_values($needsCurrent), true);
        $written = [];
        $batch = [];
        $ids = [];
        foreach ($documents as $position => $document) {
            // Successive patches to one ID must each read the preceding write.
            if (isset($document->_id) && isset($contextIds[$document->_id], $ids[$document->_id])) {
                array_push($written, ...$this->mergeBatch($batch, $needsCurrent, $processor, $activeHooks));
                $batch = [];
                $ids = [];
            }

            $batch[$position] = $document;
            if (isset($document->_id)) {
                $ids[$document->_id] = true;
            }
        }

        array_push($written, ...$this->mergeBatch($batch, $needsCurrent, $processor, $activeHooks));

        foreach ($activeHooks as $hook) {
            $hook->afterBatch($written, $this->name, $this->sigmie(), $this->properties, $this->apis);
        }

        return $this;
    }

    private function mergeBatch(array $documents, array $needsCurrent, DocumentProcessor $processor, array $hooks): array
    {
        $snapshots = $this->readPatchSources($this->name, array_intersect_key($needsCurrent, $documents));
        $patches = [];

        foreach ($documents as $position => $document) {
            $snapshot = isset($document->_id) ? ($snapshots[$document->_id] ?? null) : null;
            if (! ($snapshot['found'] ?? false)) {
                $processor->populateComboFields($document, partial: isset($document->_id));

                continue;
            }

            $embeddings = new Document(['_embeddings' => $document['_embeddings']]);
            $patches[$position] = [
                'embeddings' => $embeddings,
                'fields' => $processor->applyPatch($document, $snapshot['_source'], $this->populateEmbeddings),
            ];
        }

        if ($this->populateEmbeddings) {
            $documents = $processor->populateEmbeddingsBatch($documents, $patches);
        }

        $documents = array_values($documents);
        foreach ($hooks as $hook) {
            $documents = $hook->processBatch($documents, $this->properties, $this->apis);
        }

        $this->upsertDocuments($this->name, $documents, $this->refresh, $this->properties, $snapshots);

        return array_values($documents);
    }

    protected function processDocument(Document $document, array $activeHooks): Document
    {
        $processor = new DocumentProcessor($this->properties, $this->elasticsearchConnection->driver()->engine());
        $processor->apis($this->apis);

        $document = $processor->formatDateTimeFields($document);
        $document = $processor->validateFields($document);
        $document = $processor->populateComboFields($document);

        if ($this->populateEmbeddings) {
            $document = $processor->populateEmbeddings($document);
        }

        foreach ($activeHooks as $hook) {
            $processed = $hook->processBatch([$document], $this->properties, $this->apis);
            $document = reset($processed);
        }

        return $document;
    }

    /**
     * @return array<int, CollectionHook>
     */
    private function activeHooks(): array
    {
        if (! $this->hooksEnabled) {
            return [];
        }

        return array_values(array_filter(
            $this->hooks,
            fn (CollectionHook $hook): bool => $hook->shouldRun($this->properties)
        ));
    }

    public function toArray(): array
    {
        $documents = iterator_to_array($this->getIterator(), preserve_keys: false);
        $byId = array_column($documents, null, '_id');

        // Multiple indices or routing values can yield the same document ID.
        return count($byId) === count($documents) ? $byId : $documents;
    }

    public function clear(): void
    {
        // Delete-by-query takes a search snapshot, which excludes pending writes.
        $this->refresh();

        $refresh = $this->refresh === 'wait_for' ? 'true' : $this->refresh;

        $this->indexAPICall(sprintf('%s/_delete_by_query?refresh=%s', $this->name, $refresh), 'POST', [
            'query' => ['match_all' => (object) []],
        ]);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function remove(array|string $_id): bool
    {
        if (is_array($_id)) {
            return $this->deleteDocuments($this->name, $_id, $this->refresh);
        }

        return $this->deleteDocument($this->name, $_id, $this->refresh);
    }

    public function get(string $_id): ?Document
    {
        return $this->retrieveDocuments($this->name, [$_id])->first();
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

    public function offsetGet(mixed $offset): ?Document
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset !== null && $value instanceof Document && ($value->_id ?? null) !== (string) $offset) {
            if (isset($value->_id)) {
                $value = new Document($value->_source, (string) $offset);
            } else {
                $value->id((string) $offset);
            }
        }

        $this->add($value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove((string) $offset);
    }

    public function only(array $fields): self
    {
        $this->only = array_values($fields);

        return $this;
    }

    public function except(array $fields): self
    {
        $this->except = array_values($fields);

        return $this;
    }

    /**
     * Restrict random() to documents matching the query.
     */
    public function filterQuery(QueryClause $query): self
    {
        $this->filterQueries[] = $query;

        return $this;
    }
}
