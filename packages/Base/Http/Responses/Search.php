<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
use Sigmie\Base\Aggregations\Aggregations;
use Sigmie\Base\Documents\Collection as DocumentCollection;
use Sigmie\Base\Http\ElasticsearchResponse;

class Search extends ElasticsearchResponse
{
    public function total(): int
    {
        return $this->json('hits.total.value');
    }

    public function hits(): int
    {
        return $this->json('hits.hits');
    }

    public function aggregations(): Aggregations
    {
        return Aggregations::fromRaw($this->json('aggregations'));
    }

    public function docs(): DocumentCollectionInterface
    {
        return DocumentCollection::fromRaw($this->json('hits.hits'));
    }
}
