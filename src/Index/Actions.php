<?php

declare(strict_types=1);

namespace Sigmie\Index;

use RuntimeException;
use Sigmie\Base\APIs\Cat as CatAPI;
use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\APIs\Template;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Index\Alias\Actions as AliasActions;
use Sigmie\Index\Alias\MultipleIndicesForAlias;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Contracts\Settings as SettingsInterface;
use Sigmie\Index\Index as BaseIndex;

trait Actions
{
    use AliasActions;
    use CatAPI;
    use IndexAPI;
    use Template;

    protected function saveIndexTemplate(
        string $name,
        array $patterns,
        SettingsInterface $settings,
        MappingsInterface $mappings
    ): IndexTemplate {
        $driver = $this->elasticsearchConnection->driver();
        $patterns = array_values($patterns);

        $body = [
            'index_patterns' => $patterns,
            'settings' => $settings->toRaw(),
            'mappings' => $mappings->toRaw($driver),
        ];

        $response = $this->templateAPICall($name, 'PUT', $body);

        if ($response->json('acknowledged') !== true) {
            throw new RuntimeException(sprintf("Index template '%s' was not acknowledged.", $name));
        }

        return new IndexTemplate($name, $patterns, $settings, $mappings);
    }

    protected function createIndex(string $indexName, SettingsInterface $settings, MappingsInterface $mappings, array $aliases = []): void
    {
        $driver = $this->elasticsearchConnection->driver();

        $body = [
            'settings' => $settings->toRaw(),
            'mappings' => $mappings->toRaw($driver),
        ];

        if ($aliases !== []) {
            $body['aliases'] = (object) $aliases;
        }

        $this->indexAPICall($indexName, 'PUT', $body);
    }

    protected function indexExists(string $index): bool
    {
        $res = $this->indexAPICall($index, 'HEAD');

        return $res->code() === 200;
    }

    protected function getIndex(string $alias): BaseIndex|AliasedIndex|null
    {
        try {
            $res = $this->indexAPICall($alias, 'GET');
        } catch (ElasticsearchException $elasticsearchException) {
            $type = $elasticsearchException->json('type');

            if ($type === 'index_not_found_exception') {
                return null;
            }

            // @codeCoverageIgnoreStart
            throw $elasticsearchException;
            // @codeCoverageIgnoreEnd
        }

        $json = $res->json();

        if ($json === []) {
            return null;
        }

        if (count($json) > 1) {
            throw MultipleIndicesForAlias::forAlias($alias);
        }

        // The response key is the physical name, including resolved date math.
        $name = (string) array_key_first($json);
        $data = $json[$name];

        $settings = Settings::fromRaw($data['settings']);
        $analysis = $settings->analysis();
        $mappings = Mappings::create($data['mappings'], $analysis->analyzers(), $analysis->normalizers());

        if (isset($data['aliases']) && array_key_exists($alias, $data['aliases'])) {
            $index = new AliasedIndex($name, $alias, $settings, $mappings, $data);
            $index->setElasticsearchConnection($this->getElasticsearchConnection());

            return $index;
        }

        return new BaseIndex($name, $settings, $mappings, $data);
    }

    protected function promotedIndex(string $alias, string $destination): AliasedIndex
    {
        $index = $this->getIndex($alias);
        if (! $index instanceof AliasedIndex || $index->name !== $destination) {
            throw RebuildLost::forAlias($alias, $destination);
        }

        return $index;
    }

    protected function listIndices(string $pattern = '*'): array
    {
        $catResponse = $this->catAPICall(sprintf('indices/%s?h=index,health,status,uuid,pri,rep,docs.count,docs.deleted,store.size,pri.store.size,creation.date.string,dataset.size', $pattern), 'GET');
        $aliasesResponse = $this->catAPICall('aliases', 'GET');

        $aliasesData = $aliasesResponse->json();

        $aliasesByIndex = [];
        foreach ($aliasesData as $aliasInfo) {
            $indexName = $aliasInfo['index'];
            $aliasName = $aliasInfo['alias'];

            if (! isset($aliasesByIndex[$indexName])) {
                $aliasesByIndex[$indexName] = [];
            }

            $aliasesByIndex[$indexName][] = $aliasName;
        }

        return array_map(function (array $values) use ($aliasesByIndex): ListedIndex {
            $aliases = $aliasesByIndex[$values['index']] ?? [];

            return ListedIndex::fromRaw($values, $aliases);
        }, $catResponse->json());
    }

    protected function deleteIndex(string $name): bool
    {
        $response = $this->indexAPICall($name, 'DELETE');

        return $response->json('acknowledged');
    }

    protected function ensureIndexDeleted(string $name): void
    {
        try {
            if (! $this->deleteIndex($name)) {
                throw new RuntimeException(sprintf("Deletion of '%s' was not acknowledged.", $name));
            }
        } catch (ElasticsearchException $elasticsearchException) {
            if ($elasticsearchException->json('type') !== 'index_not_found_exception') {
                throw $elasticsearchException;
            }
        }
    }

    protected function deleteRetiredIndices(array $indices, string $destination, bool $retry): void
    {
        foreach ($indices as $index) {
            if ($index === $destination) {
                continue;
            }

            if ($retry) {
                // The swap marked each source. A replacement with the same name has no marker.
                $path = $index.'/_alias/'.$this->retiredAlias($destination);
                $status = $this->indexAPICall($path, 'HEAD')->code();
                if ($status === 404) {
                    continue;
                }

                if ($status !== 200) {
                    throw new RuntimeException(sprintf("Could not confirm retirement of '%s'.", $index));
                }
            }

            $this->ensureIndexDeleted($index);
        }
    }

    protected function checkRetirementSources(array $indices, string $alias, bool $copyDocuments = false): array
    {
        if ($indices === []) {
            return [];
        }

        $path = implode(',', $indices).'?filter_path=*.aliases,*.mappings._source,*.settings.index.blocks.write,*.settings.index.max_result_window';
        $sources = $this->indexAPICall($path, 'GET')->json();
        foreach ($indices as $index) {
            $aliases = $sources[$index]['aliases'] ?? [];
            unset($aliases[$alias]);
            if ($aliases !== []) {
                throw new RuntimeException(sprintf("Index '%s' also serves alias '", $index).array_key_first($aliases)."'.");
            }

            if (! $copyDocuments) {
                continue;
            }

            $source = $sources[$index]['mappings']['_source'] ?? [];
            $included = (array) ($source['includes'] ?? []);
            $includesAll = $included === [] || in_array('*', $included, true);
            if (($source['enabled'] ?? true) === false || ! $includesAll || ($source['excludes'] ?? []) !== []) {
                throw new RuntimeException(sprintf("Cannot copy every indexed field from '%s' because _source is disabled or filtered. Rebuild '%s' from its original documents.", $index, $alias));
            }
        }

        return $sources;
    }

    protected function updateLock(string $source): string
    {
        return '.sigmie-update-lock-'.hash('sha256', $source);
    }

    protected function updateSourceAlias(string $destination): string
    {
        return '.sigmie-update-source-'.hash('sha256', $destination);
    }

    protected function releaseUpdate(string $source, string $destination, bool $sourceWasWriteBlocked): void
    {
        $owner = $this->updateSourceAlias($destination);
        try {
            if (! $sourceWasWriteBlocked) {
                // This alias disappears with this attempt. A replayed failure
                // cannot reopen writes on behalf of the next update.
                $this->indexAPICall($owner.'/_settings', 'PUT', ['index' => ['blocks.write' => false]]);
            }

            $this->applyAliasActions($owner, [
                ['remove' => ['index' => $source, 'alias' => $owner, 'must_exist' => true]],
                ['remove' => ['index' => $destination, 'alias' => $this->updateLock($source), 'must_exist' => true]],
            ]);
        } catch (ElasticsearchException $elasticsearchException) {
            if ($elasticsearchException->getCode() !== 404) {
                throw $elasticsearchException;
            }

            // The source may have been deleted. Release this destination's
            // lock so a replacement source with the same name can be updated.
            try {
                $this->deleteAlias($destination, $this->updateLock($source));
            } catch (ElasticsearchException $cleanupError) {
                if ($cleanupError->getCode() !== 404) {
                    throw $cleanupError;
                }
            }
        }
    }

    protected function restoreReindexSettings(
        string $index,
        ?int $replicas,
        ?string $refresh,
        array $deferredBlocks = [],
        ?string $finalPipeline = null,
    ): void {
        $serverless = $this->elasticsearchConnection->isServerless();
        $settings = ['refresh_interval' => $refresh ?? ($serverless ? '5s' : '1s')];

        foreach (array_diff($deferredBlocks, NewIndex::METADATA_BLOCKS) as $block) {
            $settings[$block] = true;
        }

        if (! $serverless && $replicas !== null) {
            $settings['number_of_replicas'] = $replicas;
        }

        if ($finalPipeline !== null) {
            $settings['final_pipeline'] = $finalPipeline;
        }

        $response = $this->indexAPICall($index.'/_settings', 'PUT', $settings);

        if ($response->json('acknowledged') !== true) {
            throw new RuntimeException(sprintf("Destination settings for '%s' were not acknowledged.", $index));
        }
    }

    protected function restoreMetadataBlocks(string $index, array $deferredBlocks): void
    {
        $blocks = array_intersect(NewIndex::METADATA_BLOCKS, $deferredBlocks);
        if ($blocks !== []) {
            // Only the index. spelling may change a block the index already has.
            $this->indexAPICall($index.'/_settings', 'PUT', ['index' => array_fill_keys($blocks, true)]);
        }
    }

    protected function assertReindexSucceeded(mixed $result): void
    {
        // An update in the empty destination means two source documents shared its ID and shard.
        if (! is_array($result) || ($result['failures'] ?? null) !== []
            || ($result['timed_out'] ?? null) !== false || ($result['updated'] ?? 0) > 0) {
            throw new RuntimeException('Reindex did not complete successfully.');
        }
    }

    protected function refreshIndex(string $name): void
    {
        $this->indexAPICall($name.'/_refresh', 'POST');
    }
}
