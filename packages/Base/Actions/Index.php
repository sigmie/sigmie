<?php

declare(strict_types=1);

namespace Sigmie\Base\Actions;

use Sigmie\Base\Actions\Alias as AliasActions;
use Sigmie\Base\APIs\Cat as CatAPI;
use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\APIs\Template;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Contracts\Settings as SettingsInterface;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Base\Exceptions\IndexNotFoundException;
use Sigmie\Base\Index\AliasedIndex;
use Sigmie\Base\Index\Index as BaseIndex;
use Sigmie\Base\Index\IndexTemplate;
use Sigmie\Base\Index\Mappings;
use Sigmie\Base\Index\Settings;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;
use Sigmie\Support\Exceptions\MultipleIndicesForAlias;

trait Index
{
    use CatAPI;
    use IndexAPI;
    use AliasActions;
    use Template;

    protected function saveIndexTemplate(string $name, array $patterns, SettingsInterface $settings, MappingsInterface $mappings)
    {
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
        } catch (IndexNotFoundException) {
            return null;
        }

        if (count($res->json()) > 1) {
            throw MultipleIndicesForAlias::forAlias($alias);
        }

        $data = array_values($res->json())[0];
        $name = $data['settings']['index']['provided_name'];

        $settings = Settings::fromRaw($data['settings']);
        $analyzers = $settings->analysis()->analyzers();
        $mappings = Mappings::fromRaw($data['mappings'], $analyzers);

        if (isset($data['aliases']) && in_array($alias, array_keys($data['aliases']))) {
            $index = new AliasedIndex($name, $alias, $settings, $mappings);
        } else {
            $index = new BaseIndex($name, $settings, $mappings);
        }

        $index->setHttpConnection($this->getHttpConnection());

        return $index;
    }

    protected function getIndices(string $identifier): CollectionInterface
    {
        try {
            $res = $this->indexAPICall("{$identifier}", 'GET', );

            $collection = new Collection();

            foreach ($res->json() as $indexName => $indexData) {
                $index = AliasedIndex::fromRaw($indexName, $indexData);
                $index->setHttpConnection($this->getHttpConnection());

                $collection->add($index);
            }

            return $collection;
        } catch (ElasticsearchException) {
            return new Collection();
        }
    }

    protected function listIndices(int $offset = 0, int $limit = 100): Collection
    {
        $catResponse = $this->catAPICall('indices', 'GET', );

        return (new Collection($catResponse->json()))
            ->map(function ($values) {
                $index = new BaseIndex($values['index']);
                $index->setHttpConnection($this->getHttpConnection());

                return $index;
            });
    }

    protected function deleteIndex(string $name): bool
    {
        $response = $this->indexAPICall("{$name}", 'DELETE');

        return $response->json('acknowledged');
    }
}
