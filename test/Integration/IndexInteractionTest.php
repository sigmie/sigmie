<?php

namespace Ni\Elastic\Integration;

use Ni\Elastic\Index\Index;
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
        $response = $this->client->manage()->index()->create(['name' => 'bar']);

        $this->assertInstanceOf(SuccessResponse::class, $response);
        $this->assertTrue($response->isAcknowledged());
        $this->assertInstanceOf(Index::class, $response->getElement());
        $this->assertEquals($response->getElement()->getIdentifier(), 'bar');

        // Clean up index
        $response = $this->client->manage()->index()->remove('bar');
    }

    /**
     * @test
     */
    public function removeIndex(): void
    {
        // Create index to remove
        $response = $this->client->manage()->index()->create(['name' => 'foo']);

        $response = $this->client->manage()->index()->remove('foo');

        $this->assertInstanceOf(SuccessResponse::class, $response);
        $this->assertTrue($response->isAcknowledged());
    }

    // /**
    //  * @test
    //  */
    // public function listIndices(): void
    // {
    //     $response = $this->client->manage()->index()->list();
    // }

    // /**
    //  * @test
    //  */
    // public function getIndex(): void
    // {
    //     $response = $this->client->manage()->index()->get();
    // }
}
