<?php

declare(strict_types=1);

namespace Sigmie\Document;

use Sigmie\Base\APIs\Bulk as BulkAPI;
use Sigmie\Base\APIs\Delete as DeleteAPI;
use Sigmie\Base\APIs\Doc as DocAPI;
use Sigmie\Base\APIs\Mget as MgetAPI;
use Sigmie\Base\APIs\Search as SearchAPI;
use Sigmie\Base\APIs\Update as UpdateAPI;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Document\Document as Doc;
use Sigmie\Shared\Collection;

trait Actions
{
    use BulkAPI;
    use DeleteAPI;
    use DocAPI;
    use MgetAPI;
    use SearchAPI;
    use UpdateAPI;

    protected function updateDocument(string $indexName, Doc $document, string $refresh): Doc
    {
        if ($document->_id ?? false) {
            $body = [
                ['delete' => ['_id' => $document->_id]],
                ['create' => ['_id' => $document->_id]],
                $document->_source,
            ];
        } else {
            $body = [
                ['create' => (object) []],
                $document->_source,
            ];
        }

        $response = $this->bulkAPICall($indexName, $body, $refresh);

        if (! ($document->_id ?? false)) {
            $document->id($response->json('items.0.create._id'));
        }

        if ($response->json('errors')) {
            throw new ElasticsearchException($response->json('items.1.create.error'), $response->code());
        }

        return $document;
    }

    protected function upsertDocuments(string $indexName, array $documents, string $refresh): Collection
    {
        $body = [];
        $documents = new Collection($documents);
        $documents->each(function (Doc $document, $index) use (&$body) {

            //Upsert docs with id
            if (isset($document->_id)) {
                $body = [
                    ...$body,
                    ['update' => ['_id' => $document->_id]],
                    ['doc' => $document->_source, 'doc_as_upsert' => true],
                ];

                return;
            }
            //or

            //create docs without id
            $body = [
                ...$body,
                ['create' => (object) []],
                $document->_source,
            ];
        });

        if (count($documents) <= 0) {
            return $documents;
        }

        ray($body);
        $res = $this->bulkAPICall($indexName, $body, $refresh);

        foreach ($res->json('items') as $index => $value) {
            $action = array_key_first($value);
            $response = $value[$action];

            $doc = $documents[$index];

            if ($response['status'] >= 400) {
                throw new ElasticsearchException($response['error'], $response['status']);
            }

            if (! isset($doc->_id)) {
                $doc->id($response['_id']);
            }

            if (! isset($doc->_index)) {
                $doc->index($response['_index']);
            }
        }

        return $documents;
    }

    protected function createDocument(string $indexName, Doc $doc, string $refresh): Doc
    {
        $array = [];

        if (isset($doc->_id)) {
            $array = ['_id' => $doc->_id];
        }

        $data = [
            ['create' => (object) $array],
            $doc->_source,
        ];

        $res = $this->bulkAPICall($indexName, $data, $refresh);

        if ($res->json('errors')) {
            throw new ElasticsearchException($res->json('items.0.create.error'), $res->code());
        }

        if (! isset($doc->_id)) {
            $doc->id($res->json('items.0.create._id'));
        }

        if (! isset($doc->_index)) {
            $doc->index($res->json('items.0.create._index'));
        }

        return $doc;
    }

    protected function documentExists(string $indexName, string $_id): bool
    {
        $res = $this->docAPICall($indexName, $_id, 'HEAD');

        return $res->code() === 200;
    }

    protected function getDocument(string $indexName, string $identifier): ?Doc
    {
        $response = $this->mgetAPICall($indexName, ['docs' => [['_id' => $identifier]]]);

        return $response->first();
    }

    protected function listDocuments(string $indexName, int $offset = 0, int $limit = 100): Collection
    {
        $response = $this->searchAPICall($indexName, [
            'from' => $offset, 'size' => $limit,
            'query' => ['match_all' => (object) []],
        ]);

        $collection = new Collection();

        $values = $response->json('hits')['hits'];

        foreach ($values as $data) {
            $doc = new Doc($data['_source'], $data['_id']);
            $collection->add($doc);
        }

        return $collection;
    }

    protected function deleteDocument(string $indexName, string $identifier, string $refresh): bool
    {
        $response = $this->deleteAPICall(
            $indexName,
            $identifier,
            $refresh
        );

        return $response->code() !== 404 && $response->json('result') === 'deleted';
    }

    protected function deleteDocuments(string $indexName, array $ids, string $refresh): bool
    {
        $body = [];
        foreach ($ids as $id) {
            $body = [
                ...$body,
                ['delete' => ['_index' => $indexName, '_id' => $id]],
            ];
        }
        $response = $this->bulkAPICall($indexName, $body, $refresh);

        return $response->failed() === false;
    }
}
