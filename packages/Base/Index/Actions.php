<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Exception;
use Sigmie\Base\APIs\Cat as CatAPI;
use Sigmie\Base\APIs\Index as IndexAPI;
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

        $this->indexAPICall("/{$index->name()}", 'PUT', $body);

        $index->setHttpConnection($this->httpConnection);

        $this->events()->dispatch($index, 'index.created');

        return $index;
    }

    protected function indexExists(Index $index): bool
    {
        return $this->getIndex($index->name()) instanceof Index;
    }

    protected function getIndex(string $alias): ?AliasedIndex
    {
        try {
            $res = $this->indexAPICall("/{$alias}", 'GET', ['require_alias' => true]);

            if (count($res->json()) > 1) {
                throw new Exception("Multiple indices found for alias {$alias}.");
            }

            $data = array_values($res->json())[0];
            $name = $data['settings']['index']['provided_name'];

            $index = Index::fromRaw($name, $data);
            $index->setHttpConnection($this->getHttpConnection());

            $aliased = $index->alias($alias);
            $aliased->setHttpConnection($this->getHttpConnection());

            return $aliased;
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

                $index = Index::fromRaw($indexName, $indexData);
                $index->setHttpConnection($this->getHttpConnection());

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
