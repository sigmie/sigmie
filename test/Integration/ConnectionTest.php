<?php

namespace Ni\Elastic\Integration;

use Elasticsearch\Client as ElasticsearchClient;
use Ni\Elastic\Service\Client;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    /**
     * @test
     */
    public function connection(): void
    {
        $host = getenv('ES_HOST');
        /** @var  Client $client */
        $client = new Client([$host]);
        /** @var  ElasticsearchClient */
        $elasticsearch = $client->getElasticsearch();

        $this->assertTrue($elasticsearch->ping());
    }
}
