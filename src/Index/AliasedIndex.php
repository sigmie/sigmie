<?php

declare(strict_types=1);

namespace Sigmie\Index;

use RuntimeException;
use Sigmie\Base\APIs\Analyze;
use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\APIs\Reindex;
use Sigmie\Document\AliveCollection;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Index\Alias\Actions as AliasActions;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Contracts\Settings as SettingsInterface;

class AliasedIndex extends Index
{
    use AliasActions;
    use Analyze;
    use IndexActions;
    use IndexAPI;
    use Reindex;

    public function __construct(
        string $name,
        protected string $alias,
        ?SettingsInterface $settings = null,
        ?MappingsInterface $mappings = null
    ) {
        parent::__construct($name, $settings, $mappings);
    }

    public function collect(bool $refresh = false): AliveCollection
    {
        return new AliveCollection(
            $this->name,
            $this->elasticsearchConnection,
            $refresh ? 'true' : 'false'
        );
    }

    public function analyze(string $text, string $analyzer = 'default')
    {
        $res = $this->analyzeAPICall($this->name, $text, $analyzer);

        $tokens = array_map(fn ($token) => $token['token'], $res->json('tokens'));

        return $tokens;
    }

    public function update(callable $newUpdate): AliasedIndex
    {
        $oldAlias = $this->name;

        $update = new UpdateIndex($this->elasticsearchConnection);

        $update->alias($this->alias);
        $update->config('refresh_interval', '-1');

        $update = $newUpdate($update);

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

        $index = $this->getIndex($newAlias);

        if ($index instanceof AliasedIndex) {
            return $index;
        }

        throw new RuntimeException('Something went wrong while updating index.');
    }

    public function asyncUpdate(callable $newUpdate): IndexUpdateTask
    {
        $oldAlias = $this->name;

        $update = new UpdateIndex($this->elasticsearchConnection);

        $update->alias($this->alias);
        $update->config('refresh_interval', '-1');

        $update = $newUpdate($update);

        $blueprint = $update->make();
        $requestedReplicas = $blueprint->settings->replicaShards();

        $newAlias = $update->getAlias();
        $update->replicas(0);

        $newIndex = $update->create();

        $this->disableWrite();

        return new IndexUpdateTask(
            $this->elasticsearchConnection,
            $this->name,
            $newIndex->name,
            $oldAlias,
            $newAlias,
            $requestedReplicas
        );
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

    public function delete(): bool
    {
        return $this->deleteIndex($this->name);
    }
}
