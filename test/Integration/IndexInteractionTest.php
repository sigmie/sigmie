<?php

namespace Ni\Elastic\Integration;

use Ni\Elastic\Collection;
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

    protected function tearDown(): void
    {
        $indices = $this->client->manage()->index()->list();

        foreach ($indices as $index) {
            $this->client->manage()->index()->remove($index['index']);
        }
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

    /**
     * @test
     */
    public function listIndices(): void
    {
        $this->client->manage()->index()->create(['name' => 'foo']);
        $this->client->manage()->index()->create(['name' => 'bar']);

        $collection = $this->client->manage()->index()->list();

        $this->assertEquals($collection[0]['index'],'foo');
        $this->assertEquals($collection[1]['index'],'bar');
        $this->assertCount(2, $collection);
        $this->assertInstanceOf(Collection::class, $collection);
    }

    // /**
    //  * @test
    //  */
    // public function getIndex(): void
    // {
    //     $response = $this->client->manage()->index()->get();
    // }
}
