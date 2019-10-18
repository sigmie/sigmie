<?php

namespace Sigma\Test\Unit;

use PHPUnit\Framework\TestCase;
use Sigma\Client;
use Elasticsearch\Client as Elasticsearch;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ClientTest extends TestCase
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var Elasticsearch
     */
    private $esMock;

    /**
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function setUp(): void
    {
        $this->esMock = $this->createMock(Elasticsearch::class);

        $this->client = Client::create($this->esMock);
    }

    /**
     * @test
     */
    public function isConnected(): void
    {
        $this->esMock->method('ping')->willReturn(true);
        $this->esMock->expects($this->once())->method('ping');

        $result = $this->client->isConnected();

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function eventDispatcher(): void
    {
        $eventDispatcher = $this->client->events();

        $this->assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);
    }

    /**
     * @test
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function elasticsearch(): void
    {
        $client = Client::create();
        $elasticsearch = $client->elasticsearch();

        $this->assertInstanceOf(Elasticsearch::class, $elasticsearch);
    }
}
