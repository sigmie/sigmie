<?php

use Elasticsearch\ClientBuilder;

require_once 'vendor/autoload.php';

$esClient = ClientBuilder::create()->build();

$params = [
    'index' => $name,
    'body' => [
        'settings' => [
            'number_of_shards' => $shards,
            'number_of_replicas' => $replicas
        ]
    ]
];

$esClient->indicies()->create($params);
