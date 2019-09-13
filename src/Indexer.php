<?php

namespace niElastic;

use Elasticsearch\Client;

class Indexer
{
    private $esClient;

    public function __construc(Client $esClient)
    {
        $this->esClient = $esClient;
    }


    public function createIndex($name, $shards = 2, $replicas = 0)
    {
        $params = [
            'index' => $name,
            'body' => [
                'settings' => [
                    'number_of_shards' => $shards,
                    'number_of_replicas' => $replicas
                ]
            ]
        ];

        $this->esClient->indicies()->create($params);
    }
}
