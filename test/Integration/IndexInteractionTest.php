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

        $indices = $this->client->getElasticsearch()->cat()->indices(['index' => '*']);

        foreach ($indices as $index) {
            $this->client->getElasticsearch()->indices()->delete(['index' => $index['index']]);
        }
    }

    /**
     * @test
     */
    public function createIndex(): void
    {
        $index = new Index('bar');
        $result = $this->client->manage()->index()->create($index);

        $this->assertTrue($result->exists());
        $this->assertInstanceOf(Index::class, $result);
        $this->assertEquals($result->getIdentifier(), 'bar');

        // Clean up created index
        $this->client->manage()->index()->remove('bar');
    }

    /**
     * @test
     */
    public function removeIndex(): void
    {
        // Create index to be remove
        $this->client->manage()->index()->create(new Index('bar'));

        $response = $this->client->manage()->index()->remove('bar');

        $this->assertTrue($response);
    }

    /**
     * @test
     */
    public function listIndices(): void
    {
        $this->client->manage()->index()->create(new Index('foo'));
        $this->client->manage()->index()->create(new Index('bar'));

        $collection = $this->client->manage()->index()->list();

        $this->assertCount(2, $collection);
        $this->assertInstanceOf(Collection::class, $collection);
    }

    /**
     * @test
     */
    public function getIndex(): void
    {
        $this->client->manage()->index()->create(new Index('foo'));

        $response = $this->client->manage()->index()->get('foo');
        $element = $response->first();

        $this->assertInstanceOf(Index::class, $element);
        $this->assertEquals('foo', $element->getIdentifier());
    }
}
