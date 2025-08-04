<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Contracts\Settings as SettingsInterface;

class ListedIndex
{
    public function __construct(
        public readonly string $name,
        public readonly string $health,
        public readonly string $status,
        public readonly string $uuid,
        public readonly int $primaryShards,
        public readonly int $replicaShards,
        public readonly int $documentsCount,
        public readonly int $documentsDeleted,
        public readonly string $storeSize,
        public readonly string $primaryStoreSize,
        public readonly string $datasetSize,
        public readonly ?array $raw = null
    ) {}

    public static function fromRaw(string $name, array $raw): static
    {
        $index = new static(
            $name,
            health: $raw['health'],
            status: $raw['status'],
            uuid: $raw['uuid'],
            primaryShards: (int) $raw['pri'],
            replicaShards: (int) $raw['rep'],
            documentsCount: (int) $raw['docs.count'],
            documentsDeleted: (int) $raw['docs.deleted'],
            storeSize: (string) $raw['store.size'],
            primaryStoreSize: (string) $raw['pri.store.size'],
            datasetSize: (string) $raw['pri.store.size'],
        );

        return $index;
    }
}
