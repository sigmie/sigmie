<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchResponse;

class Bulk extends ElasticsearchResponse
{
    public function assertItemsSucceeded(): void
    {
        self::assertOperationsSucceeded($this->items());
    }

    public static function assertOperationsSucceeded(array $operations): void
    {
        $failures = [];

        foreach ($operations as $operation) {
            $item = reset($operation);
            if (isset($item['error']) || (($item['status'] ?? 200) >= 400 && array_key_first($operation) !== 'delete')
                || ($item['_shards']['failed'] ?? 0) > 0) {
                $failures[] = $item;
            }
        }

        if ($failures === []) {
            return;
        }

        $first = $failures[0];
        $error = $first['error'] ?? ['type' => 'bulk_item_failed'];

        throw new ElasticsearchException([
            ...$first,
            ...(is_array($error) ? $error : ['type' => $error]),
            'failures' => $failures,
        ], $first['status'] ?? 0);
    }

    public function items(): array
    {
        $items = $this->json('items') ?? [];
        if ($items === [] && $this->json('errors') === true) {
            throw new ElasticsearchException($this->json(), $this->code());
        }

        return $items;
    }
}
