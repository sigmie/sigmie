<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\AliasedIndex;
use Sigmie\Base\APIs\Cat as CatAPI;
use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\APIs\Template;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Contracts\Settings as SettingsInterface;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Exceptions\IndexNotFoundException;
use Sigmie\Index\Index as BaseIndex;
use Sigmie\Base\Index\IndexTemplate;
use Sigmie\Index\Mappings;
use Sigmie\Index\Settings;
use Sigmie\Index\Alias\Actions as AliasActions;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;
use Sigmie\Index\Alias\MultipleIndicesForAlias;

trait Actions
{
    use CatAPI;
    use IndexAPI;
    use AliasActions;
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
        $res = $this->indexAPICall("{$alias}", 'GET');

        if (count($res->json()) > 1) {
            throw MultipleIndicesForAlias::forAlias($alias);
        }

        $data = array_values($res->json())[0];
        $name = $data['settings']['index']['provided_name'];

        $settings = Settings::fromRaw($data['settings']);
        $analyzers = $settings->analysis()->analyzers();
        $mappings = Mappings::create($data['mappings'], $analyzers);


        if (isset($data['aliases']) && in_array($alias, array_keys($data['aliases']))) {
            $index = new AliasedIndex($name, $alias, $settings, $mappings);
        } else {
            $index = new BaseIndex($name, $settings, $mappings);
        }

        $index->setElasticsearchConnection($this->getElasticsearchConnection());

        return $index;
    }

    protected function listIndices(string $patter = '*'): array
    {
        $catResponse = $this->catAPICall('indices', 'GET');

        return array_map(function ($values) {
            $index = new BaseIndex($values['index']);
            $index->setElasticsearchConnection($this->getElasticsearchConnection());

            return $index;
        }, $catResponse->json());
    }

    protected function deleteIndex(string $name): bool
    {
        $response = $this->indexAPICall("{$name}", 'DELETE');

        return $response->json('acknowledged');
    }
}
