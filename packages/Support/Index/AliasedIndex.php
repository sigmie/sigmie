<?php

declare(strict_types=1);

namespace Sigmie\Support\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\APIs\Reindex;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Index\Index;
use Sigmie\Base\Index\Mappings;
use Sigmie\Base\Index\Settings;
use Sigmie\Base\Mappings\Properties;
use function Sigmie\Helpers\index_name;
use Sigmie\Support\Update\Update;
use Sigmie\Support\Update\UpdateProxy;

class AliasedIndex extends Index
{
    use Reindex, IndexAPI;

    public function __construct(
        string $identifier,
        protected string $alias,
        array $aliases,
        ?Settings $settings = null,
        ?MappingsInterface $mappings = null,
    ) {
        parent::__construct($identifier, $aliases, $settings, $mappings);
    }

    public function update(callable $update): AliasedIndex
    {
        $oldAlias = $this->alias;

        $update = (new UpdateProxy($this->httpConnection, $this->alias))($update);

        $requestedReplicas = $update->make()->getSettings()->replicaShards();

        $newAlias = $update->make()->alias;
        $update->replicas(0);

        $newIndex = $update->create();

        $this->disableWrite();

        $this->reindexAPICall($this->name(), $newIndex->name());

        $this->indexAPICall("/{$newIndex->name()}/_settings", 'PUT', [
            'number_of_replicas' => $requestedReplicas,
            'refresh_interval' => '1s'
        ]);

        if ($oldAlias === $newAlias) {
            $this->switchAlias($newAlias, $this->name(), $newIndex->name());
        } else {
            $this->createAlias($newIndex->name(), $newAlias);
        }

        $this->deleteIndex($this->name());

        return $this->getIndex($newAlias);
    }

    public function toRaw(): array
    {
        $res = parent::toRaw();

        $res['alias'] = $this->alias;

        return $res;
    }

    public function disableWrite(): void
    {
        $this->indexAPICall("/{$this->name()}/_settings", 'PUT', [
            'index' => ['blocks.write' => true]
        ]);
    }

    public function enableWrite(): void
    {
        $this->indexAPICall("/{$this->name()}/_settings", 'PUT', [
            'index' => ['blocks.write' => false]
        ]);
    }
}
