<?php

declare(strict_types=1);

namespace Sigmie\Index;

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
        public readonly array $aliases = []
    ) {}

    public static function fromRaw(array $raw, array $aliases = []): static
    {
        $index = new static(
            $raw['index'],
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
            aliases: $aliases,
        );

        return $index;
    }
}
