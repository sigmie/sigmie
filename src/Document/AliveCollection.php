<?php

declare(strict_types=1);

namespace Sigmie\Document;

use ArrayAccess;
use Countable;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Document\Actions as DocumentActions;
use Sigmie\Document\Contracts\DocumentCollection;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Index\Shared\Mappings;
use Sigmie\Mappings\Properties;
use Sigmie\Semantic\DocumentProcessor;
use Traversable;
use Sigmie\Shared\Collection;
use Sigmie\Shared\UsesApis;

class AliveCollection implements ArrayAccess, Countable, DocumentCollection
{
    use DocumentActions;
    use IndexActions;
    use LazyEach;
    use Search;
    use Mappings;
    use UsesApis;

    protected ?array $only = null;

    protected ?array $except = null;

    protected bool $populateEmbeddings = true;

    public function __construct(
        protected string $name,
        ElasticsearchConnection $connection,
        protected string $refresh = 'false'
    ) {
        $this->setElasticsearchConnection($connection);

        $this->properties = new Properties();
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
        $document = $this->documentEmbeddings($document);

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

        $collection = new Collection();

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
        $document = $this->documentEmbeddings($document);

        $document = $this->createDocument($this->name, $document, $this->refresh);

        return $document;
    }

    public function merge(array $docs): AliveCollection
    {
        $docs = array_map(fn(Document $doc): Document => $this->processDocument($doc), $docs);

        $this->upsertDocuments($this->name, $docs, $this->refresh);

        return $this;
    }

    protected function processDocument(Document $document): Document
    {
        $documentProcessor = new DocumentProcessor($this->properties);
        $documentProcessor->apis($this->apis);

        $document = $documentProcessor->formatDateTimeFields($document);
        $document = $documentProcessor->validateFields($document);
        $document = $documentProcessor->populateComboFields($document);

        if ($this->populateEmbeddings) {
            return $documentProcessor->populateEmbeddings($document);
        }

        return $document;
    }

    private function documentEmbeddings(Document $document): Document
    {
        return $this->processDocument($document);
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
