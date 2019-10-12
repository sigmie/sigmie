<?php

namespace Ni\Elastic\Test\Integration;

use Ni\Elastic\Collection;
use Ni\Elastic\Index\Index;
use Ni\Elastic\Response\SuccessResponse;
use Ni\Elastic\Service\Client;
use PHPUnit\Framework\TestCase;
use Elasticsearch\ClientBuilder;

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
        $builder = ClientBuilder::create();
        $es = $builder->setHosts([$host])->build();
        $this->client = Client::create($es);

        $indices = $this->client->elasticsearch()->cat()->indices(['index' => '*']);

        foreach ($indices as $index) {
            $this->client->elasticsearch()->indices()->delete(['index' => $index['index']]);
        }
    }

    /**
     * @test
     */
    public function createIndex(): void
    {
        $index = new Index('bar');
        $result = $this->client->manage()->indices()->create($index);

        $this->assertTrue($result);

        $element = $this->client->manage()->indices()->get('bar');

        $this->assertInstanceOf(Index::class, $element);
        $this->assertEquals($element->getIdentifier(), 'bar');

        // Clean up created index
        $this->client->manage()->indices()->remove('bar');
    }

    /**
     * @test
     */
    public function removeIndex(): void
    {
        // Create index to be remove
        $this->client->manage()->indices()->create(new Index('bar'));

        $response = $this->client->manage()->indices()->remove('bar');

        $this->assertTrue($response);
    }

    /**
     * @test
     */
    public function listIndices(): void
    {
        $this->client->manage()->indices()->create(new Index('foo'));
        $this->client->manage()->indices()->create(new Index('bar'));

        $collection = $this->client->manage()->indices()->list();

        $this->assertCount(2, $collection);
        $this->assertInstanceOf(Collection::class, $collection);
    }

    /**
     * @test
     */
    public function getIndex(): void
    {
        $this->client->manage()->indices()->create(new Index('foo'));

        $element = $this->client->manage()->indices()->get('foo');

        $this->assertInstanceOf(Index::class, $element);
        $this->assertEquals('foo', $element->getIdentifier());
    }
}
