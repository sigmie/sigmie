<?php

declare(strict_types=1);

namespace Sigmie\Index;

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
    ) {
        $body = [
            'index_patterns' => $patterns,
            'settings' => $settings->toRaw(),
            'mappings' => $mappings->toRaw(),
        ];

        $this->templateAPICall($name, 'PUT', $body);

        return new IndexTemplate($name, $patterns, $settings, $mappings);
    }

    protected function createIndex(string $indexName, SettingsInterface $settings, MappingsInterface $mappings)
    {
        $body = [
            'settings' => $settings->toRaw(),
            'mappings' => $mappings->toRaw(),
        ];

        $this->indexAPICall("{$indexName}", 'PUT', $body);
    }

    protected function indexExists(string $index): bool
    {
        $res = $this->indexAPICall("{$index}", 'HEAD');

        return $res->code() === 200;
    }

    protected function getIndex(string $alias): BaseIndex|AliasedIndex|null
    {
        try {
            $res = $this->indexAPICall("{$alias}", 'GET');
        } catch (ElasticsearchException $e) {
            $type = $e->json('type');

            if ($type === 'index_not_found_exception') {
                return null;
            }

            throw $e;
        }

        if (count($res->json()) > 1) {
            throw MultipleIndicesForAlias::forAlias($alias);
        }

        $data = array_values($res->json())[0];
        $name = $data['settings']['index']['provided_name'];

        $settings = Settings::fromRaw($data['settings']);
        $analyzers = $settings->analysis()->analyzers();
        $mappings = Mappings::create($data['mappings'], $analyzers);

        if (isset($data['aliases']) && in_array($alias, array_keys($data['aliases']))) {
            $index = new AliasedIndex($name, $alias, $settings, $mappings, $data);
            $index->setElasticsearchConnection($this->getElasticsearchConnection());

            return $index;
        }

        $index = new BaseIndex($name, $settings, $mappings, $data);

        return $index;
    }

    protected function listIndices(string $pattern = '*'): array
    {
        $catResponse = $this->catAPICall("indices/{$pattern}?h=index,health,status,uuid,pri,rep,docs.count,docs.deleted,store.size,pri.store.size,creation.date.string", 'GET');
        $aliasesResponse = $this->catAPICall("aliases", 'GET');

        $aliasesData = $aliasesResponse->json();
        
        $aliasesByIndex = [];
        foreach ($aliasesData as $aliasInfo) {
            $indexName = $aliasInfo['index'];
            $aliasName = $aliasInfo['alias'];
            
            if (!isset($aliasesByIndex[$indexName])) {
                $aliasesByIndex[$indexName] = [];
            }
            $aliasesByIndex[$indexName][] = $aliasName;
        }

        return array_map(function ($values) use ($aliasesByIndex) {
            $aliases = $aliasesByIndex[$values['index']] ?? [];
            
            $index = ListedIndex::fromRaw($values, $aliases);

            return $index;
        }, $catResponse->json());
    }

    protected function deleteIndex(string $name): bool
    {
        $response = $this->indexAPICall("{$name}", 'DELETE');

        return $response->json('acknowledged');
    }

    protected function refreshIndex(string $name): void
    {
        // {"_shards":{"total":3,"successful":1,"failed":0}}
        $this->indexAPICall("{$name}/_refresh", 'POST');
    }
}
