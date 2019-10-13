<?php

namespace Ni\Elastic\Test\Integration;

use Ni\Elastic\Client;
use PHPUnit\Framework\TestCase;
use Elasticsearch\ClientBuilder;

class ConnectionTest extends TestCase
{
    /**
     * @test
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function connection(): void
    {
        $host = getenv('ES_HOST');
        $builder = ClientBuilder::create();
        $elasticsearch = $builder->setHosts([$host])->build();
        $client = Client::create($elasticsearch);

        $elasticsearch = $client->elasticsearch();

        $this->assertTrue($elasticsearch->ping());
    }
}
