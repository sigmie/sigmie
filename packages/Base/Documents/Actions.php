<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use function Amp\Parallel\Worker\enqueue;
use function Amp\Promise\all;
use function Amp\Promise\wait;
use Exception;
use Sigmie\Base\APIs\Bulk as BulkAPI;
use Sigmie\Base\APIs\Delete as DeleteAPI;
use Sigmie\Base\APIs\Mget as MgetAPI;
use Sigmie\Base\APIs\Search as SearchAPI;
use Sigmie\Base\APIs\Update as UpdateAPI;
use Sigmie\Base\Contracts\API;

use Sigmie\Base\Contracts\DocumentCollection;
use Sigmie\Base\Search\Query;
use Sigmie\Support\BulkBody;

trait Actions
{
    use SearchAPI, DeleteAPI, MgetAPI, BulkAPI, UpdateAPI, API;

    public function updateDocument(Document $document): Document
    {
        $body = [
            ['update' => ['_id' => $document->getId()]],
            ['doc' => $document->attributes()],
        ];

        $response = $this->bulkAPICall($document->getIndex()->name(), $body);

        if ($response->failed()) {
            throw new Exception('Document update failed.');
        }

        return $document;
    }

    protected function upsertDocuments(DocumentCollection &$documentCollection): DocumentCollection
    {
        $indexName = $this->index()->name();
        $body = [];
        $documentCollection->forAll(function ($index, Document $document) use (&$body) {
            $body = [
                ...$body,
                ['update' => ($document->getId() !== null) ? ['_id' => $document->getId()] : (object) []],
                ['doc' => $document->attributes(), 'doc_as_upsert' => true],
            ];
        });

        $response = $this->bulkAPICall($indexName, $body);

        $ids = [];

        foreach ($response->getAll() as [$action, $item]) {
            $ids[] = $item['_id'];
        }

        $tempCollection = $documentCollection;
        $documentCollection = new DocumentsCollection();

        // The bulk api response order is guaranteed see:
        // https://discuss.elastic.co/t/ordering-of-responses-in-the-bulk-api/13264
        $tempCollection->forAll(function ($index, Document $doc) use ($ids, &$documentCollection) {
            $id = $ids[$index];
            $doc->setId($id);
            $documentCollection[$id] = $doc;
        });

        return $documentCollection;
    }

    /**
     * @param bool $async Should we wait for the
     * document to become available
     */
    protected function createDocument(Document &$doc, bool $async): Document
    {
        $indexName = $this->index()->name();
        $array = [];

        if ($doc->getId() !== null) {
            $array = ['_id' => $doc->getId()];
        }

        $data = [
            ['create' => (object) $array],
            $doc->attributes(),
        ];

        $res = $this->bulkAPICall($indexName, $data, $async);

        [[$rest, $data]] = $res->getAll();

        $doc->setId($data['_id']);

        return $doc;
    }

    protected function createDocuments(DocumentCollection &$documentCollection, bool $async): DocumentCollection
    {
        $indexName = $this->index()->name();
        $body = [];
        $docs = $documentCollection->toArray();

        $docsChunk = array_chunk($docs, 2);

        $promises = [];
        foreach ($docsChunk as $docs) {
            $promises[] = enqueue(new BulkBody($docs));
        }

        $all = wait(all($promises));

        $body = array_merge(...$all);

        $response = $this->bulkAPICall($indexName, $body, $async);

        $ids = [];

        foreach ($response->getAll() as [$action, $item]) {
            $ids[] = $item['_id'];
        }

        $tempCollection = $documentCollection;
        $documentCollection = new DocumentsCollection();

        // The bulk api response order is guaranteed see:
        // https://discuss.elastic.co/t/ordering-of-responses-in-the-bulk-api/13264
        $tempCollection->forAll(function (Document $doc, $index) use ($ids, &$documentCollection) {
            $id = $ids[$index];
            $doc->setId($id);
            $documentCollection[$id] = $doc;
        });

        return $documentCollection;
    }

    protected function getDocument(string $identifier): ?Document
    {
        $response = $this->mgetAPICall(['docs' => [['_id' => $identifier]]]);

        return $response->first();
    }

    protected function listDocuments(int $offset = 0, int $limit = 100): DocumentCollection
    {
        $response = $this->searchAPICall($this->index()->name(), [
            'from' => $offset, 'size' => $limit,
            'query' => ['match_all' => (object) []]
        ]);

        $collection = new DocumentsCollection();

        $values = $response->json('hits')['hits'];

        foreach ($values as $data) {
            $doc = new Document($data['_source'], $data['_id']);
            $collection->addDocument($doc);
        }

        return $collection;
    }

    protected function deleteDocument(string $identifier): bool
    {
        $response = $this->deleteAPICall(
            identifier: $identifier,
        );

        return $response->json('result') === 'deleted';
    }

    protected function deleteDocuments(array $ids): bool
    {
        $indexName = $this->index()->name();

        $body = [];
        foreach ($ids as $id) {
            $body = [
                ...$body,
                ['delete' => ['_index' => $indexName, '_id' => $id]],
            ];
        }
        $response = $this->bulkAPICall($indexName, $body);

        return $response->failed() === false;
    }
}
