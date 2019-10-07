<?php

namespace Ni\Elastic\Integration;

use Elasticsearch\Client as ElasticsearchClient;
use Ni\Elastic\Service\Client;
use PHPUnit\Framework\TestCase;
use Elasticsearch\ClientBuilder;

class ConnectionTest extends TestCase
{
    /**
     * @test
     */
    public function connection(): void
    {
        $host = getenv('ES_HOST');
        $builder = ClientBuilder::create();
        $es = $builder->setHosts([$host])->build();
        $client = Client::create($es);

        $elasticsearch = $client->elasticsearch();

        $this->assertTrue($elasticsearch->ping());
    }
}
