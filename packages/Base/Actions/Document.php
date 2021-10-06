<?php

declare(strict_types=1);

namespace Sigmie\Base\Actions;

use function Amp\Parallel\Worker\enqueue;
use function Amp\Promise\all;
use function Amp\Promise\wait;
use Exception;
use Sigmie\Base\APIs\Bulk as BulkAPI;
use Sigmie\Base\APIs\Delete as DeleteAPI;
use Sigmie\Base\APIs\Doc as DocAPI;
use Sigmie\Base\APIs\Mget as MgetAPI;
use Sigmie\Base\APIs\Search as SearchAPI;
use Sigmie\Base\APIs\Update as UpdateAPI;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Documents\DocumentCollection;
use Sigmie\Base\Index\AbstractIndex;
use Sigmie\Base\Search\Query;
use Sigmie\Support\BulkBody;
use Sigmie\Base\Documents\Document as Doc;

trait Document
{
    use SearchAPI, DeleteAPI, MgetAPI, BulkAPI, UpdateAPI, API, DocAPI;

    public function updateDocument(string $indexName, Doc $document, string $refresh): Doc
    {
        $body = [
            ['update' => ['_id' => $document->_id]],
            ['doc' => $document->_source],
        ];

        $response = $this->bulkAPICall($indexName, $body, $refresh);

        if ($response->failed()) {
            throw new Exception('Document update failed.');
        }

        return $document;
    }

    protected function upsertDocuments(string $indexName, DocumentCollectionInterface $collection, string $refresh): DocumentCollectionInterface
    {
        $body = [];
        $collection->each(function (Doc $document, $index) use (&$body) {
            if (!is_null($document->_id)) {
                $body = [
                    ...$body,
                    ['update' => ['_id' => $document->_id]],
                    ['doc' => $document->_source, 'doc_as_upsert' => true],
                ];
                return;
            }

            $body = [
                ...$body,
                ['create' => (object) []],
                $document->_source
            ];
        });

        //TODO fix mapping wrong ids when some of the
        //documents have failed
        $successful = $this->bulkAPICall($indexName, $body, $refresh)->getSuccessful();

        foreach ($successful as $index => [$action, $values]) {
            $collection[$index]->_id = $values['_id'];
        }

        return $collection;
    }

    /**
     * @param bool $async Should we wait for the
     * document to become available
     */
    protected function createDocument(string $indexName, Doc $doc, string $refresh): Doc
    {
        $array = [];

        if ($doc->_id !== null) {
            $array = ['_id' => $doc->_id];
        }

        $data = [
            ['create' => (object) $array],
            $doc->_source,
        ];

        $res = $this->bulkAPICall($indexName, $data, $refresh);

        $data = $res->getAll()->first()['create'];

        if (is_null($doc->_id)) {
            $doc->_id = $data['_id'];
        }

        return $doc;
    }

    protected function documentExists(string $indexName, string $_id): bool
    {
        $res = $this->docAPICall($indexName, $_id, 'HEAD');

        return $res->code() === 200;
    }

    protected function createDocuments(string $indexName, DocumentCollection $documentCollection, string $refresh): DocumentCollection
    {
        $body = [];
        $docs = $documentCollection->toArray();

        $docsChunk = array_chunk($docs, 2);

        $promises = [];
        foreach ($docsChunk as $docs) {
            $promises[] = enqueue(new BulkBody($docs));
        }

        $all = wait(all($promises));

        $body = array_merge(...$all);

        $response = $this->bulkAPICall($indexName, $body, $refresh);

        $ids = $response->getAll()->map(fn ($value) => $value['create']['_id']);

        $index = 0;
        return $documentCollection->each(function (Doc $doc) use ($ids, &$index) {
            if (is_null($doc->_id)) {
                $doc->_id = $ids[$index];
            }
            $index++;
        });

        return $documentCollection;
    }

    protected function getDocument(string $indexName, string $identifier): ?Doc
    {
        $response = $this->mgetAPICall($indexName, ['docs' => [['_id' => $identifier]]]);

        return $response[0];
    }

    protected function listDocuments(string $indexName, int $offset = 0, int $limit = 100): DocumentCollection
    {
        $response = $this->searchAPICall($indexName, [
            'from' => $offset, 'size' => $limit,
            'query' => ['match_all' => (object) []]
        ]);

        $collection = new DocumentCollection();

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

        return $response->json('result') === 'deleted';
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
