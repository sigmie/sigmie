<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Document\Document;
use Sigmie\Shared\Collection;

class Mget extends ElasticsearchResponse
{
    protected Collection $collection;

    public function __construct(ResponseInterface $psrResponse)
    {
        $this->response = $psrResponse;

        $collection = new Collection($this->json('docs'));

        $this->collection = $collection
            ->filter(fn ($value) => $value['found'] ?? false)
            ->map(fn ($values) => Document::fromRaw($values));
    }

    public function docs(): array
    {
        return $this->collection->toArray();
    }

    public function first(): null|Document
    {
        return $this->collection->first();
    }
}
