<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\ElasticsearchException;
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
            ->filter(function (array $item): bool {
                if (isset($item['error'])) {
                    $error = $item['error'];

                    throw new ElasticsearchException(
                        is_array($error) ? $error : ['type' => (string) $error],
                        $item['status'] ?? 0,
                    );
                }

                return $item['found'] ?? (isset($item['_source']) || isset($item['fields']));
            })
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
