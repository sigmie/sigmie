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

    protected bool $populateEmbeddings = true;

    public function __construct(
        protected string $name,
        ElasticsearchConnection $connection,
        protected string $refresh = 'false'
    ) {
        $this->setElasticsearchConnection($connection);

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
        $document = $this->processDocument($document);

        return $this->updateDocument($this->name, $document, $this->refresh);
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
        $response = $this->searchAPICall($this->name, [
            'from' => 0,
            'size' => $size,
            'query' => [
                'function_score' => [
                    'query' => ['match_all' => (object) []],
                    'random_score' => (object) [],
                    'boost_mode' => 'replace',
                ],
            ],
        ]);

        $collection = new Collection;

        $values = $response->json('hits')['hits'];

        foreach ($values as $data) {
            $doc = new Document($data['_source'], $data['_id']);
            $collection->add($doc);
        }

        return $collection;
    }

    public function take(int $limit): array
    {
        if ($limit > 0) {
            return $this->listDocuments($this->name, 0, $limit)->toArray();
        }

        $total = $this->count();
        $pageSize = abs($limit);
        $lastPage = (int) ceil($total / $pageSize);
        $offset = ($lastPage - 1) * $pageSize;

        return $this->listDocuments($this->name, $offset, $pageSize)->toArray();
    }

    public function add(Document $document): Document
    {
        $activeHooks = $this->activeHooks();

        foreach ($activeHooks as $hook) {
            $hook->beforeBatch($this->name, $this->sigmie(), $this->properties, $this->apis);
        }

        $document = $this->processDocument($document);

        $document = $this->createDocument($this->name, $document, $this->refresh);

        foreach ($activeHooks as $hook) {
            $hook->afterBatch([$document], $this->name, $this->sigmie(), $this->properties, $this->apis);
        }

        return $document;
    }

    public function merge(array $docs): AliveCollection
    {
        $activeHooks = $this->activeHooks();

        foreach ($activeHooks as $hook) {
            $hook->beforeBatch($this->name, $this->sigmie(), $this->properties, $this->apis);
        }

        $processor = new DocumentProcessor($this->properties);
        $processor->apis($this->apis);

        $docs = array_map(function (Document $doc) use ($processor): Document {
            $doc = $processor->formatDateTimeFields($doc);
            $doc = $processor->validateFields($doc);
            $doc = $processor->populateComboFields($doc);

            return $doc;
        }, $docs);

        if ($this->populateEmbeddings) {
            $docs = array_map(fn (Document $doc): Document => $processor->populateEmbeddings($doc), $docs);
        }

        foreach ($activeHooks as $hook) {
            $docs = $hook->processBatch($docs, $this->properties, $this->apis);
        }

        $this->upsertDocuments($this->name, $docs, $this->refresh);

        foreach ($activeHooks as $hook) {
            $hook->afterBatch($docs, $this->name, $this->sigmie(), $this->properties, $this->apis);
        }

        return $this;
    }

    protected function processDocument(Document $document): Document
    {
        $processor = new DocumentProcessor($this->properties);
        $processor->apis($this->apis);

        $document = $processor->formatDateTimeFields($document);
        $document = $processor->validateFields($document);
        $document = $processor->populateComboFields($document);

        if ($this->populateEmbeddings) {
            $document = $processor->populateEmbeddings($document);
        }

        foreach ($this->activeHooks() as $hook) {
            [$document] = $hook->processBatch([$document], $this->properties, $this->apis);
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
        return iterator_to_array($this->getIterator());
    }

    public function clear(): void
    {
        $this->indexAPICall(sprintf('%s/_delete_by_query?refresh=%s', $this->name, $this->refresh), 'POST', [
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
        return $this->getDocument($this->name, $_id);
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

    public function offsetSet(mixed $_id, mixed $doc): void
    {
        $this->add($doc);
    }

    public function offsetUnset(mixed $_id): void
    {
        $this->remove((string) $_id);
    }

    public function only(array $fields): self
    {
        $this->only = $fields;

        return $this;
    }

    public function except(array $fields): self
    {
        $this->except = $fields;

        return $this;
    }
}
