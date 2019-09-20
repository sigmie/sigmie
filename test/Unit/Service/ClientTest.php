<?php

namespace Ni\Elastic\Test\Service;

use Elasticsearch\Client as ElasticsearchClient;
use Ni\Elastic\Service\Client;
use PHPUnit\Framework\TestCase;
use Elasticsearch\ClientBuilder;

class ClientTest extends TestCase
{
    /**
     * Builder mock
     *
     * @var ClientBuilder
     */
    private $builderMock;

    /**
     * Elasticsearch mock
     *
     * @var ElasticsearchClient
     */
    private $esMock;

    public function setUp(): void
    {
        /** @var ElasticsearchClient $esMock */
        $this->esMock = $this->createMock(ElasticsearchClient::class);
        /** @var ClientBuilder $builderMock */
        $this->builderMock = $this->createMock(ClientBuilder::class);
        $this->builderMock->method('setHosts')->willReturn($this->builderMock);
        $this->builderMock->method('build')->willReturn($this->esMock);
    }
    /**
     * @test
     */
    public function constructorElasticsearch(): void
    {
        $client = new Client([], $this->esMock);
        $this->assertEquals($client->getElasticsearch(), $this->esMock);
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
        $client = new Client([], $this->esMock, $this->builderMock);
        $this->assertEquals($this->builderMock, $client->getBuilder());
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
        $this->builderMock
            ->expects($this->once())
            ->method('setHosts')
            ->with(['foo']);

        new Client(['foo'], null, $this->builderMock);
    }

    /**
     * @test
     */
    public function getterMethods(): void
    {
        $client = new Client(['foo'], null, $this->builderMock);

        $this->assertEquals(['foo'], $client->getHosts());
        $this->assertInstanceOf(ElasticsearchClient::class, $client->getElasticsearch());
        $this->assertEquals($this->builderMock, $client->getBuilder());
    }

    /**
     * @test
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function setterMethods(): void
    {
        $client = new Client(['foo'], $this->esMock, $this->builderMock);

        $builder = ClientBuilder::create();
        $elasticsearch = $builder->build();

        $client->setElasticsearch($elasticsearch);
        $client->setBuilder($builder);
        $client->setHosts(['127.0.0.1']);

        $this->assertEquals($elasticsearch, $client->getElasticsearch());
        $this->assertEquals($builder, $client->getBuilder());
        $this->assertEquals(['127.0.0.1'], $client->getHosts());
    }
}
