<?php

namespace Ni\Elastic\Integration;

use Ni\Elastic\Response\SuccessResponse;
use Ni\Elastic\Service\Client;
use PHPUnit\Framework\TestCase;

class IndexInteractionTest extends TestCase
{
    /**
     * Client instance
     *
     * @var Client
     */
    private $client;

    public function setup(): void
    {
        $host = getenv('ES_HOST');
        /** @var  Client $client */
        $this->client = new Client([$host]);
    }

    /**
     * @test
     */
    public function createIndex(): void
    {
        $response = $this->client->manager()->index()->create(['name' => 'products']);

        $this->assertInstanceOf(SuccessResponse::class, $response);
        // $this->assertTrue($response['acknowledged']);
        // $this->assertEquals('products', $response['index']);
    }

    /**
     * @test
     */
    public function removeIndex(): void
    {
        $response = $this->client->manager()->index()->remove('products');

        $this->assertInstanceOf(SuccessResponse::class, $response);
        $this->assertTrue($response->isAcknowledged());
    }
}
