<?php

declare(strict_types=1);

namespace Sigmie\Base\Actions;

use Exception;
use Sigmie\Base\APIs\Bulk as BulkAPI;
use Sigmie\Base\APIs\Delete as DeleteAPI;
use Sigmie\Base\APIs\Doc as DocAPI;
use Sigmie\Base\APIs\Mget as MgetAPI;
use Sigmie\Base\APIs\Search as SearchAPI;
use Sigmie\Base\APIs\Update as UpdateAPI;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Documents\Collection;
use Sigmie\Base\Documents\Document as Doc;

trait Document
{
    use SearchAPI;
    use DeleteAPI;
    use MgetAPI;
    use BulkAPI;
    use UpdateAPI;
    use API;
    use DocAPI;

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

        if (!($document->_id ?? false)) {
            $document->id($response->json('items.0.create._id'));
        }

        if ($response->failed()) {
            throw new Exception('Document update failed.');
        }

        return $document;
    }

    protected function upsertDocuments(string $indexName, DocumentCollectionInterface $collection, string $refresh): DocumentCollectionInterface
    {
        $body = [];
        $collection->each(function (Doc $document, $index) use (&$body) {
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

        $res = $this->bulkAPICall($indexName, $body, $refresh);

        foreach ($res->json('items') as $index => $value) {
            $action = array_key_first($value);
            $response = $value[$action];

            $doc = $collection[$index];
            if (!isset($doc->_id)) {
                $doc->id($response['_id']);
            }

            if (!isset($doc->_index)) {
                $doc->index($response['_index']);
            }
        }

        return $collection;
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

        if (!isset($doc->_id)) {
            $doc->id($res->json('items.0.create._id'));
        }

        if (!isset($doc->_index)) {
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

        return $response->docs()->get('0');
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
