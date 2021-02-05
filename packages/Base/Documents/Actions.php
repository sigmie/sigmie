<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use Sigmie\Base\APIs\Calls\Bulk as BulkAPI;
use Sigmie\Base\APIs\Calls\Delete as DeleteAPI;
use Sigmie\Base\APIs\Calls\Mget as MgetAPI;
use Sigmie\Base\APIs\Calls\Search as SearchAPI;
use Sigmie\Base\APIs\Calls\Update as UpdateAPI;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\DocumentCollection;
use Sigmie\Base\Search\Query;

trait Actions
{
    use SearchAPI, DeleteAPI, MgetAPI, BulkAPI, UpdateAPI, API;

    protected function upsertDocuments(DocumentCollection &$documentCollection): DocumentCollection
    {
        $indexName = $this->index()->getName();
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
        $indexName = $this->index()->getName();
        $array = [];

        if ($doc->getId() !== null) {
            $array = ['_id' => $doc->getId()];
        }

        $data = [
            ['create' => (object) $array],
            $doc->attributes(),
        ];

        $res = $this->bulkAPICall($indexName, $data, $async);

        [[, $data]] = $res->getAll();

        $doc->setId($data['_id']);

        return $doc;
    }

    protected function createDocuments(DocumentCollection &$documentCollection, bool $async): DocumentCollection
    {
        $indexName = $this->index()->getName();
        $body = [];
        $documentCollection->forAll(function ($index, Document $document) use (&$body) {
            $body = [
                ...$body,
                ['create' => ($document->getId() !== null) ? ['_id' => $document->getId()] : (object) []],
                $document->attributes(),
            ];
        });

        $response = $this->bulkAPICall($indexName, $body, $async);

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

    protected function getDocument(string $identifier): ?Document
    {
        $response = $this->mgetAPICall(['docs' => [['_id' => $identifier]]]);

        return $response->first();
    }

    protected function listDocuments($offset = 0, $limit = 100): DocumentCollection
    {
        $query = new Query(['match_all' => (object) []]);
        $query->index($this->index());
        $query->setFrom($offset)->setSize($limit);

        $response = $this->searchAPICall($query);

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
        $response = $this->deleteAPICall($identifier);

        return $response->json('result') === 'deleted';
    }

    protected function deleteDocuments(array $ids): bool
    {
        $indexName = $this->index()->getName();

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
