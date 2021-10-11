<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use LogicException;
use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Documents\Collection as DocumentCollectionActions;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Documents\DocumentCollection;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Support\Collection;

class Search extends ElasticsearchResponse implements DocumentCollectionInterface
{
    use DocumentCollectionActions;

    public function __construct(ResponseInterface $psr)
    {
        parent::__construct($psr);
    }

    public function add(Document $document): DocumentCollectionInterface
    {
        throw new LogicException('Mget response data may not be mutated');
    }

    public function merge(array|DocumentCollectionInterface $documents): DocumentCollectionInterface
    {
        throw new LogicException('Mget response data may not be mutated');
    }
}
