<?php

namespace Ni\Elastic\Test\Service;

use Elasticsearch\Client as ElasticsearchClient;
use Ni\Elastic\Service\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    /**
     * @test
     */
    public function defaultHost(): void
    {
        $client = new Client();
        $this->assertEquals($client->gethost(), '127.0.0.1');
    }

    /**
     * @test
     */
    public function defaultPort(): void
    {
        $client = new Client();
        $this->assertEquals($client->getPort(), '9200');
    }

    /**
     * @test
     */
    public function constructorArguments(): void
    {
        /** @var ElasticsearchClient $esMock */
        $esMock = $this->createMock(ElasticsearchClient::class);
        $client = new Client('192.168.0.1', '3100', $esMock);

        $this->assertEquals($client->getHost(), '192.168.0.1');
        $this->assertEquals($client->getPort(), '3100');
        $this->assertEquals($client->getElasticsearch(), $esMock);
    }

    /**
     * @test
     */
    public function getElasticsearch(): void
    {
        $client = new Client('192.168.0.1', '9200');

        $this->assertInstanceOf(ElasticsearchClient::class, $client->getElasticsearch());
    }
}
