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
use Throwable;

use function Sigmie\Functions\response_value;

class AliasedIndex extends Index
{
    use AliasActions;
    use Analyze;
    use IndexActions;
    use IndexAPI;
    use Reindex;

    /** @var array{alias: string, destination: string, blocks: list<string>, sourceDeleted: bool}|null */
    private ?array $pendingCutover = null;

    public function __construct(
        string $name,
        protected string $alias,
        ?SettingsInterface $settings = null,
        ?MappingsInterface $mappings = null,
        ?array $raw = null
    ) {
        parent::__construct($name, $settings, $mappings, $raw);
    }

    public static function fromRaw(string $name, array $raw): static
    {
        $aliases = $raw['aliases'] ?? [];
        $alias = is_array($aliases) ? array_key_first($aliases) : null;

        if (! is_string($alias) && ! is_int($alias)) {
            throw new RuntimeException('An aliased index payload needs an alias name.');
        }

        $settings = Settings::fromRaw($raw);
        $analysis = $settings->analysis();
        $mappings = Mappings::create($raw['mappings'], $analysis->analyzers(), $analysis->normalizers());

        // The class stores one alias. A payload with several keeps the first name.
        return new static($name, (string) $alias, $settings, $mappings, $raw);
    }

    public function collect(bool $refresh = false): AliveCollection
    {
        return new AliveCollection(
            $this->alias,
            $this->elasticsearchConnection,
            $refresh ? 'true' : 'false',
            requireAlias: true,
        );
    }

    public function analyze(string $text, string $analyzer = 'default'): array
    {
        $res = $this->analyzeAPICall($this->name, $text, $analyzer);

        return array_map(fn ($token) => $token['token'], $res->json('tokens'));
    }

    public function update(callable $newUpdate): AliasedIndex
    {
        if ($this->pendingCutover !== null) {
            return $this->finishUpdate(retry: true);
        }

        [$update, $requestedReplicas, $requestedRefresh, $deferredBlocks, $sourceWasWriteBlocked, $batchSize] = $this->preparedUpdate($newUpdate);
        $index = $update->make();
        $newAlias = $update->getAlias();
        $this->claimUpdate($index);

        try {
            $finalPipeline = $this->suspendFinalPipeline($index->name);
            $this->blockWrites($this->updateSourceAlias($index->name));
            $this->refreshIndex($this->name);
            $result = $this->reindexAPICall($this->name, $index->name, pipeline: '_none', batchSize: $batchSize);
            $this->assertReindexSucceeded($result->json());

            $this->restoreReindexSettings($index->name, $requestedReplicas, $requestedRefresh, $deferredBlocks, $finalPipeline);
            $this->moveAlias($newAlias, [$this->name], $index->name, $this->alias, pendingAlias: $this->updateLock($this->name),
                markRetired: true, sourceMarker: $this->updateSourceAlias($index->name));
        } catch (Throwable $throwable) {
            $this->rollbackWriteBlock($throwable, $sourceWasWriteBlocked, $index->name);
        }

        $this->pendingCutover = ['alias' => $newAlias, 'destination' => $index->name, 'blocks' => $deferredBlocks, 'sourceDeleted' => false];

        return $this->finishUpdate(retry: false);
    }

    private function finishUpdate(bool $retry): AliasedIndex
    {
        ['alias' => $alias, 'destination' => $destination, 'blocks' => $blocks] = $this->pendingCutover;

        if (! $this->pendingCutover['sourceDeleted']) {
            $this->deleteRetiredIndices([$this->name], $destination, $retry);
            $this->pendingCutover['sourceDeleted'] = true;
        }

        $updated = $this->promotedIndex($alias, $destination);
        $this->restoreMetadataBlocks($destination, $blocks);
        $this->pendingCutover = null;

        return $updated;
    }

    public function asyncUpdate(callable $newUpdate): IndexUpdateTask
    {
        [$update, $requestedReplicas, $requestedRefresh, $deferredBlocks, $sourceWasWriteBlocked, $batchSize] = $this->preparedUpdate($newUpdate);
        $index = $update->make();
        $this->claimUpdate($index);

        try {
            $finalPipeline = $this->suspendFinalPipeline($index->name);
            $this->blockWrites($this->updateSourceAlias($index->name));
            $this->refreshIndex($this->name);

            return new IndexUpdateTask(
                $this->elasticsearchConnection,
                $this->name,
                $index->name,
                $this->alias,
                $update->getAlias(),
                $requestedReplicas,
                $requestedRefresh,
                deferredBlocks: $deferredBlocks,
                sourceWasWriteBlocked: $sourceWasWriteBlocked,
                finalPipeline: $finalPipeline,
                ownsUpdateLock: true,
                batchSize: $batchSize,
            );
        } catch (Throwable $throwable) {
            $this->rollbackWriteBlock($throwable, $sourceWasWriteBlocked, $index->name);
        }
    }

    private function claimUpdate(Index $destination): void
    {
        $this->createIndex($destination->name, $destination->settings, $destination->mappings);

        // Elasticsearch permits one write index per alias. Acquire the lock
        // and its attempt-specific rollback target in one cluster-state update.
        $this->applyAliasActions($this->alias, [
            ['add' => ['index' => $this->name, 'alias' => $this->updateSourceAlias($destination->name), 'is_hidden' => true]],
            ['add' => ['index' => $destination->name, 'alias' => $this->updateLock($this->name), 'is_hidden' => true, 'is_write_index' => true]],
        ]);
    }

    private function rollbackWriteBlock(Throwable $cause, bool $sourceWasWriteBlocked, string $destination): never
    {
        try {
            $this->releaseUpdate($this->name, $destination, $sourceWasWriteBlocked);
        } catch (Throwable $throwable) {
            throw new RuntimeException(sprintf("Could not restore writes to '%s' after the update failed. ", $this->name).$throwable->getMessage(), $throwable->getCode(), previous: $cause);
        }

        throw $cause;
    }

    /**
     * The callback runs before the temporary refresh of -1. That -1 is only
     * the build setting, so it is written when the callback omitted
     * refresh_interval. A value the callback set, including -1, stays on the
     * created index and is restored after reindex. An omitted value becomes
     * 1s, or 5s when the connection is serverless.
     *
     * @return array{0: UpdateIndex, 1: ?int, 2: ?string, 3: list<string>, 4: bool, 5: int}
     */
    private function preparedUpdate(callable $newUpdate): array
    {
        $sources = $this->checkRetirementSources([$this->name], $this->alias, copyDocuments: true);
        $sourceWasWriteBlocked = in_array(
            response_value($sources[$this->name] ?? [], 'settings.index.blocks.write'),
            [true, 'true'],
            true,
        );
        // A scroll batch larger than the source's max_result_window is rejected.
        $batchSize = min(1000, (int) (response_value($sources[$this->name] ?? [], 'settings.index.max_result_window') ?? 1000));
        $update = new UpdateIndex($this->elasticsearchConnection);
        $update->alias($this->alias);
        $update = $newUpdate($update);

        $blueprint = $update->make();
        $requestedReplicas = $blueprint->settings->replicaShards();
        $refresh = $blueprint->settings->toRaw()['refresh_interval'] ?? null;
        $requestedRefresh = is_string($refresh) || is_int($refresh) ? (string) $refresh : null;

        // The callback can retain its builder. Apply temporary settings to our copy.
        $build = clone $update;
        if ($requestedRefresh === null) {
            $build->config('refresh_interval', '-1');
        }

        $build->useBulkReplicas();

        return [$build, $requestedReplicas, $requestedRefresh, $build->deferAccessBlocks(), $sourceWasWriteBlocked, $batchSize];
    }

    private function suspendFinalPipeline(string $destination): ?string
    {
        // Also read inherited templates. A reindex request only skips the default pipeline.
        $path = $destination.'/_settings';
        $settings = $this->indexAPICall($path.'?filter_path=*.settings.index.final_pipeline', 'GET')->json();
        $pipeline = $settings[$destination]['settings']['index']['final_pipeline'] ?? null;
        if ($pipeline === null || $pipeline === '_none') {
            return null;
        }

        $this->indexAPICall($path, 'PUT', ['final_pipeline' => '_none']);

        return $pipeline;
    }

    public function disableWrite(): void
    {
        $this->blockWrites($this->name);
    }

    private function blockWrites(string $target): void
    {
        $response = $this->indexAPICall($target.'/_block/write', 'PUT');

        $alreadyBlocked = $response->json('acknowledged') === true && $response->json('indices') === [];
        if ($response->json('shards_acknowledged') !== true && ! $alreadyBlocked) {
            throw new RuntimeException(sprintf("Write block for '%s' was not acknowledged by all shards.", $this->name));
        }
    }

    public function enableWrite(): void
    {
        $this->indexAPICall($this->name.'/_settings', 'PUT', [
            'index' => ['blocks.write' => false],
        ]);
    }

    public function delete(): bool
    {
        return $this->deleteIndex($this->name);
    }

    public function get(?string $path = null): mixed
    {
        return $path === null ? ($this->raw ?? []) : response_value($this->raw ?? [], $path);
    }
}
