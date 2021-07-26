<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Support\Collection as SupportCollection;

class DocumentsCollection implements DocumentCollectionInterface
{
    use Collection;

    /**
     * @param array<Document> $documents
     */
    public function __construct(array $documents = [])
    {
        $this->collection = new SupportCollection();

        foreach ($documents as $doc) {
            $this->addDocument($doc);
        }
    }
}
