<?php

declare(strict_types=1);

namespace Sigmie\Base\Actions;

use App\Models\Mapping;
use Sigmie\Base\APIs\Cat as CatAPI;
use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\Contracts\Mappings;
use Sigmie\Base\Contracts\Settings;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Base\Index\AliasedIndex;
use Sigmie\Base\Index\Index as BaseIndex;
use Sigmie\Base\Actions\Alias as AliasActions;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;
use Sigmie\Support\Exceptions\MultipleIndices;

trait Index
{
    use CatAPI, IndexAPI, AliasActions;

    protected function createIndex(string $indexName, Settings $settings, Mappings $mappings)
    {
        $body = [
            'settings' => $settings->toRaw(),
            'mappings' => $mappings->toRaw()
        ];

        $this->indexAPICall("/{$indexName}", 'PUT', $body);
    }

    protected function indexExists(string $index): bool
    {
        $res = $this->indexAPICall("/{$index}", 'HEAD');

        return $res->code() === 200;
    }

    protected function getIndex(string $alias): ?BaseIndex
    {
        try {
            $res = $this->indexAPICall("/{$alias}", 'GET', ['require_alias' => true]);

            if (count($res->json()) > 1) {
                //TODO this depends on support
                throw MultipleIndices::forAlias($alias);
            }

            $data = array_values($res->json())[0];
            $name = $data['settings']['index']['provided_name'];

            // if (isset($data['aliases']) && in_array($alias, array_keys($data['aliases']))) {
            //     $index = AliasedIndex::fromRaw($name, $data);
            // } else {
            $index = BaseIndex::fromRaw($name, $data);
            // }
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
        $catResponse = $this->catAPICall('/indices', 'GET',);

        return (new Collection($catResponse->json()))
            ->map(function ($values) {
                $index = new BaseIndex($values['index']);
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
