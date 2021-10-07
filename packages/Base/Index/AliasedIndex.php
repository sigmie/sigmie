<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\APIs\Reindex;
use Sigmie\Base\Index\AbstractIndex; use Sigmie\Base\Index\Settings;
use Sigmie\Base\Actions\Alias as AliasActions;
use Sigmie\Base\Actions\Index as IndexActions;
use Sigmie\Support\Update\Update;
use Sigmie\Support\Update\UpdateProxy;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Contracts\Settings as SettingsInterface;

class AliasedIndex extends Index
{
    use Reindex, IndexAPI, AliasActions, IndexActions;

    public function __construct(
        protected string $name,
        protected string $alias,
        SettingsInterface $settings = null,
        MappingsInterface $mappings = null
    ) {
        $this->settings = $settings ?: new Settings();
        $this->mappings = $mappings ?: new Mappings();
    }


    public function update(callable $update): AliasedIndex|Index
    {
        $oldAlias = $this->name;

        $update = (new UpdateProxy($this->httpConnection, $this->alias))($update);

        $blueprint = $update->make();
        $requestedReplicas = $blueprint->settings->replicaShards();

        $newAlias = $update->getAlias();
        $update->replicas(0);

        $newIndex = $update->create();

        $this->disableWrite();

        $this->reindexAPICall($this->name, $newIndex->name);

        $this->indexAPICall("/{$newIndex->name}/_settings", 'PUT', [
            'number_of_replicas' => $requestedReplicas,
            'refresh_interval' => '1s'
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
        $this->indexAPICall("/{$this->name}/_settings", 'PUT', [
            'index' => ['blocks.write' => true]
        ]);
    }

    public function enableWrite(): void
    {
        $this->indexAPICall("/{$this->name}/_settings", 'PUT', [
            'index' => ['blocks.write' => false]
        ]);
    }
}
