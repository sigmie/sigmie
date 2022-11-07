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

    public function aggregation(string $dot): mixed
    {
        return $this->json("aggregations.{$dot}");
    }

    public function get():array
    {
        return $this->json();
    }

    public function hits(): array
    {
        return $this->json("hits.hits");
    }
}
