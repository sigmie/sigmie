<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Documents\Collection as  DocumentCollection;
use Sigmie\Base\Http\ElasticsearchResponse;

class Mget extends ElasticsearchResponse
{
    public function docs(): DocumentCollectionInterface
    {
        $found = array_filter($this->json('docs'), fn ($value) => $value['found'] ?? false);

        return DocumentCollection::fromRaw($found);
    }
}
