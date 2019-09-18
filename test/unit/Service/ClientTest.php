<?php

namespace Ni\Elastic\Test\Service;

use Elasticsearch\Client as ElasticsearchClient;
use Ni\Elastic\Service\Client;
use PHPUnit\Framework\TestCase;
use Elasticsearch\ClientBuilder;

class ClientTest extends TestCase
{
    /**
     * @test
     */
    public function constructorElasticsearch(): void
    {
        /** @var ElasticsearchClient $esMock */
        $esMock = $this->createMock(ElasticsearchClient::class);
        $client = new Client([], $esMock);
        $this->assertEquals($client->getElasticsearch(), $esMock);
    }

    /**
     * @test
     */
    public function methodElasticsearch(): void
    {
        $client = new Client([]);
        $this->assertInstanceOf(ElasticsearchClient::class, $client->elasticsearch());
    }

    /**
     * @test
     */
    public function constructorBuilder(): void
    {
        /** @var ClientBuilder $builderMock */
        $builderMock = $this->createMock(ClientBuilder::class);
        /** @var ElasticsearchClient $esMock */
        $esMock = $this->createMock(ElasticsearchClient::class);
        $client = new Client([], $esMock, $builderMock);
        $this->assertEquals($builderMock, $client->getBuilder());
    }

    /**
     * @test
     */
    public function defaultBuilder(): void
    {
        $client = new Client([]);
        $this->assertInstanceOf(ClientBuilder::class, $client->getBuilder());
    }

    /**
     * @test
     */
    public function methodBuild(): void
    {
        /** @var ElasticsearchClient $esMock */
        $esMock = $this->createMock(ElasticsearchClient::class);
        /** @var ClientBuilder $bulderMock */
        $builderMock = $this->createMock(ClientBuilder::class);
        $builderMock->method('setHosts')->willReturn($builderMock);
        $builderMock->method('build')->willReturn($esMock);

        $builderMock->expects($this->once())
            ->method('setHosts')->with(['foo']);

        new Client(['foo'], null, $builderMock);
    }
}
