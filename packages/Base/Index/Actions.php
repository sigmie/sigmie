<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\APIs\Calls\Cat as CatAPI;
use Sigmie\Base\APIs\Calls\Index as IndexAPI;
use Sigmie\Base\Contracts\Events;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Support\Collection;

trait Actions
{
    use CatAPI, IndexAPI, Events;

    protected function createIndex(Index $index): Index
    {
        $settings = $index->getSettings();
        $mappings = $index->getMappings();

        $body = [
            'settings' => $settings->toRaw(),
            'mappings' => $mappings->toRaw()
        ];

        $this->indexAPICall("/{$index->getName()}", 'PUT', $body);

        $index->setHttpConnection($this->httpConnection);

        $this->events()->dispatch($index, 'index.created');

        return $index;
    }

    protected function indexExists(Index $index): bool
    {
        return $this->getIndex($index->getName()) instanceof Index;
    }

    protected function getIndex(string $identifier): ?Index
    {
        try {
            $res = $this->indexAPICall("/{$identifier}", 'GET', ['require_alias' => true]);

            $data = array_values($res->json())[0];

            $name = $data['settings']['index']['provided_name'];
            $aliases = $data['aliases'];
            $settings = Settings::fromRaw($data);
            $analyzers = $settings->analysis->analyzers();

            $index = new Index($name, $settings, Mappings::fromRaw($data['mappings'], $analyzers));

            // if (count($aliases) > 0) {
            //     foreach ($aliases as $alias => $value) {
            //         $index->setAlias($alias);
            //     }
            // }

            $index->setHttpConnection($this->getHttpConnection());

            return $index;
        } catch (ElasticsearchException) {
            return null;
        }
    }

    protected function getIndices(string $identifier)
    {
        try {
            $res = $this->indexAPICall("/{$identifier}", 'GET',);

            $collection = new Collection();

            foreach ($res->json() as $indexName => $indexData) {

                [$prefix, $res] = preg_split("/_/", $indexName);

                $index = new Index($indexName);
                $index->setHttpConnection($this->getHttpConnection());
                $index->setPrefix($prefix);

                $collection->add($index);
            }

            return $collection;
        } catch (ElasticsearchException) {
            return new Collection();
        }
    }

    protected function listIndices($offset = 0, $limit = 100): Collection
    {
        $catResponse = $this->catAPICall('/indices', 'GET',);

        return (new Collection($catResponse->json()))
            ->map(function ($values) use ($catResponse) {
                $index = new Index($values['index']);
                $index->setHttpConnection($this->getHttpConnection());
                $index->setSize($values['store.size']);

                return $index;
            });
    }

    protected function deleteIndex(string $name): bool
    {
        $response = $this->indexAPICall("/{$name}", 'DELETE');

        return $response->json('acknowledged');
    }
}
