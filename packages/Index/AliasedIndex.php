<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\Alias\Actions as AliasActions;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\APIs\Reindex;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Contracts\Settings as SettingsInterface;
use Sigmie\Index\UpdateProxy;

class AliasedIndex extends Index
{
    use Reindex;
    use IndexAPI;
    use AliasActions;
    use IndexActions;

    public function __construct(
        protected string $name,
        protected string $alias,
        SettingsInterface $settings = null,
        MappingsInterface $mappings = null
    ) {
        $this->settings = $settings ?: new Settings();
        $this->mappings = $mappings ?: new Mappings();
    }


    public function update(callable $newUpdate): AliasedIndex|Actions
    {
        $oldAlias = $this->name;

        $update = new UpdateIndex($this->elasticsearchConnection);

        $update->alias($this->alias);
        $update->config('refresh_interval', '-1');

        $newUpdate($update);

        $blueprint = $update->make();
        $requestedReplicas = $blueprint->settings->replicaShards();

        $newAlias = $update->getAlias();
        $update->replicas(0);

        $newIndex = $update->create();

        $this->disableWrite();

        $this->reindexAPICall($this->name, $newIndex->name);

        $this->indexAPICall("{$newIndex->name}/_settings", 'PUT', [
            'number_of_replicas' => $requestedReplicas,
            'refresh_interval' => '1s',
        ]);

        if ($oldAlias === $newAlias) {
            $this->switchAlias($newAlias, $this->name, $newIndex->name);
        } else {
            $this->createAlias($newIndex->name, $newAlias);
        }

        $this->deleteIndex($this->name);

        return $this->getIndex($newAlias);
    }

    public function disableWrite(): void
    {
        $this->indexAPICall("{$this->name}/_settings", 'PUT', [
            'index' => ['blocks.write' => true],
        ]);
    }

    public function enableWrite(): void
    {
        $this->indexAPICall("{$this->name}/_settings", 'PUT', [
            'index' => ['blocks.write' => false],
        ]);
    }
}
