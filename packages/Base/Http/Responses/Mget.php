<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use LogicException;
use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Documents\Collection as DocumentCollection;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Documents\DocumentsCollection;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Support\Collection;

class Mget extends ElasticsearchResponse implements DocumentCollectionInterface
{
    use DocumentCollection;

    public function __construct(ResponseInterface $psr)
    {
        parent::__construct($psr);

        $this->collection = new Collection();

        $this->createCollection($this->json('docs'));
    }

    public function toDocumentCollection(): DocumentCollectionInterface
    {
        return new DocumentsCollection($this->collection->toArray());
    }

    public function addDocument(Document $element): DocumentCollectionInterface
    {
        throw new LogicException('Mget response data may not be mutated');
    }

    public function addDocuments(array|DocumentCollectionInterface $documents): DocumentCollectionInterface
    {
        throw new LogicException('Mget response data may not be mutated');
    }

    private function createCollection(array $docsData)
    {
        foreach ($docsData as $documentData) {
            if ($documentData['found'] === false) {
                continue;
            }

            $document = Document::fromRaw($documentData);

            $this->collection->add($document);
        }
    }
}
