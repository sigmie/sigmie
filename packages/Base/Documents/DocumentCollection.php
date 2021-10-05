<?php

declare(strict_types=1);

namespace Sigmie\Base\Documents;

use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Documents\Collection as DocumentCollectionActions;
use Sigmie\Support\Collection;

class DocumentCollection implements DocumentCollectionInterface
{
    use DocumentCollectionActions;

    /**
     * @param array<Document> $documents
     */
    public function __construct(array $documents = [])
    {
        $this->collection = new Collection();

        foreach ($documents as $doc) {
            $this->add($doc);
        }
    }
}
