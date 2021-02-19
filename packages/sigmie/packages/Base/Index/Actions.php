<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use PhpParser\Node\Expr\Instanceof_;
use Sigmie\Base\APIs\Calls\Cat as CatAPI;
use Sigmie\Base\APIs\Calls\Index as IndexAPI;
use Sigmie\Base\Exceptions\NotFound;
use Sigmie\Support\Collection;

use function PHPUnit\Framework\isInstanceOf;

trait Actions
{
    use CatAPI, IndexAPI;

    protected function createIndex(Index $index): Index
    {
        $settings = $index->getSettings();

        $settings = [
            'settings' => [
                'number_of_shards' => $settings->primaryShards,
                'number_of_replicas' => $settings->replicaShards,
            ],
        ];

        $this->indexAPICall("/{$index->getName()}", 'PUT', $settings);

        $index->setHttpConnection(self::$httpConnection);

        return $index;
    }

    protected function indexExists(Index $index): bool
    {
        return $this->getIndex($index->getName()) instanceof Index;
    }


    protected function getIndex(string $identifier): ?Index
    {
        try {
            $res = $this->indexAPICall("/{$identifier}", 'GET',);

            $data = array_values($res->json())[0];
            $name = $data['settings']['index']['provided_name'];
            $aliases = $data['aliases'];
            $index = new Index($name);

            if (count($aliases) > 0) {
                foreach ($aliases as $alias => $value) {
                    $index->setAlias($alias);
                }
            }

            $index->setHttpConnection($this->getHttpConnection());

            return $index;
        } catch (NotFound) {
            return null;
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
