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
use Sigmie\Support\Index\AliasedIndex;

trait Actions
{
    use CatAPI, IndexAPI, AliasActions;

    protected function createIndex(Index $index): Index
    {
        $settings = $index->settings;
        $mappings = $index->mappings;

        $body = [
            'settings' => $settings->toRaw(),
            'mappings' => $mappings->toRaw()
        ];

        $this->indexAPICall("/{$index->name}", 'PUT', $body);

        $index->setHttpConnection($this->httpConnection);

        return $index;
    }

    protected function indexExists(Index $index): bool
    {
        return $this->getIndex($index->name) instanceof Index;
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

            $index = Index::fromRaw($name, $data);
            $index->setHttpConnection($this->getHttpConnection());

            $aliased = $index->alias($alias);
            $aliased->setHttpConnection($this->getHttpConnection());

            return $aliased;
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

                $index = Index::fromRaw($indexName, $indexData);
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
                $index = new Index($values['index']);
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
