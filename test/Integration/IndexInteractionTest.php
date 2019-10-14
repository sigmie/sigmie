<?php

namespace Sigma\Test\Integration;

use Elasticsearch\ClientBuilder;
use Sigma\Client;
use Sigma\Collection;
use Sigma\Index\Index;
use PHPUnit\Framework\TestCase;

class IndexInteractionTest extends TestCase
{
    /**
     * Client instance
     *
     * @var Client
     */
    private $client;

    /**
     * Setup stubs
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     *
     * @return void
     */
    public function setup(): void
    {
        $host = getenv('ES_HOST');
        $builder = ClientBuilder::create();
        $elasticsearch = $builder->setHosts([$host])->build();
        $this->client = Client::create($elasticsearch);

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
