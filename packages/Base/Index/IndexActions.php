<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\APIs\Cat as CatAPI;
use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Support\Alias\Actions as AliasActions;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;
use Sigmie\Support\Exceptions\MultipleIndices;
use Sigmie\Base\Index\AliasedIndex;

trait IndexActions
{
    use CatAPI, IndexAPI, AliasActions;

    protected function createIndex(string $indexName, IndexBlueprint $index)
    {
        $settings = $index->settings;
        $mappings = $index->mappings;

        $body = [
            'settings' => $settings->toRaw(),
            'mappings' => $mappings->toRaw()
        ];

        $this->indexAPICall("/{$indexName}", 'PUT', $body);
    }

    protected function indexExists(AbstractIndex $index): bool
    {
        return $this->getIndex($index->name) instanceof AbstractIndex;
    }

    protected function getIndex(string $alias): ?AliasedIndex
    {
        try {
            $res = $this->indexAPICall("/{$alias}", 'GET', ['require_alias' => true]);

            if (count($res->json()) > 1) {
                //TODO this depends on support
                throw MultipleIndices::forAlias($alias);
            }

            $data = array_values($res->json())[0];
            $name = $data['settings']['index']['provided_name'];

            $index = new AliasedIndex($name, $alias);
            $index->setHttpConnection($this->getHttpConnection());

            return $index;
        } catch (ElasticsearchException) {
            return null;
        }
    }

    protected function getIndices(string $identifier): CollectionInterface
    {
        try {
            $res = $this->indexAPICall("/{$identifier}", 'GET',);

            $collection = new Collection();

            foreach ($res->json() as $indexName => $indexData) {

                $index = AbstractIndex::fromRaw($indexName, $indexData);
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
        $catResponse = $this->catAPICall('/indices', 'GET',);

        return (new Collection($catResponse->json()))
            ->map(function ($values) {
                $index = new ActiveIndex($values['index']);
                $index->setHttpConnection($this->getHttpConnection());

                return $index;
            });
    }

    protected function deleteIndex(string $name): bool
    {
        $response = $this->indexAPICall("/{$name}", 'DELETE');

        return $response->json('acknowledged');
    }
}
