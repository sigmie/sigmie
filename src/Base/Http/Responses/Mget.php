<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Document\Document;
use Sigmie\Shared\Collection;

class Mget extends ElasticsearchResponse
{
    protected Collection $collection;

    private function createCollection(): void
    {
        $collection = new Collection($this->json('docs') ?? []);

        $this->collection = $collection
            ->filter(fn ($value) => $value['found'] ?? false)
            ->map(fn ($values): Document => Document::fromRaw($values));
    }

    public function docs(): array
    {
        if (! isset($this->collection)) {
            $this->createCollection();
        }

        return $this->collection->toArray();
    }

    public function first(): ?Document
    {
        if (! isset($this->collection)) {
            $this->createCollection();
        }

        return $this->collection->first();
    }
}
