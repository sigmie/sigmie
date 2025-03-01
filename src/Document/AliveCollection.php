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
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Shared\Mappings;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Shared\EmbeddingsProvider;
use Traversable;

class AliveCollection implements ArrayAccess, Countable, DocumentCollection
{
    use DocumentActions;
    use IndexActions;
    use LazyEach;
    use Search;
    use Mappings;
    use EmbeddingsProvider;

    public function __construct(
        protected string $name,
        ElasticsearchConnection $connection,
        protected string $refresh = 'false'
    ) {
        $this->setElasticsearchConnection($connection);

        $this->properties = new Properties();
    }


    public function refresh()
    {
        $this->refreshIndex($this->name);

        return $this;
    }

    public function replace(Document $document): Document
    {
        $document = $this->documentEmbeddings($document);

        $doc = $this->updateDocument($this->name, $document, $this->refresh);

        return $doc;
    }

    public function all(): Traversable
    {
        return $this->getIterator();
    }

    public function has(string $_id): bool
    {
        return $this->documentExists($this->name, $_id);
    }

    public function take(int $limit)
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
        $docs = array_map(fn(Document $doc) => $this->documentEmbeddings($doc), $docs);

        $collection = $this->upsertDocuments($this->name, $docs, $this->refresh);

        return $this;
    }

    private function documentEmbeddings(Document $document): Document
    {
        $embeddings = [];

        $this->properties->nestedSemanticFields()
            ->each(function (Text $field, $name) use (&$embeddings, $document) {

                $text = dot($document->_source)->get($name);

                if ($text) {
                    $embeddings[$name] = $this->embeddingsProvider->embed($text);
                }
            });

        $document['embeddings'] = $embeddings;

        return $document;
    }

    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

    public function clear(): void
    {
        $this->indexAPICall("{$this->name}/_delete_by_query?refresh={$this->refresh}", 'POST', [
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
}
