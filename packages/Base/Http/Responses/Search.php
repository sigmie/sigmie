<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\Contracts\DocumentCollection as DocumentCollectionInterface;
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

    public function aggregation(string $dot): mixed
    {
        return $this->json("aggregations.{$dot}");
    }

    public function docs(): DocumentCollectionInterface
    {
        return DocumentCollection::fromRaw($this->json('hits.hits'));
    }
}
