<?php

namespace Sigma\Test\Integration;

use Elasticsearch\ClientBuilder;
use Sigma\Sigma;
use Sigma\Collection;
use Sigma\Index\Index;
use PHPUnit\Framework\TestCase;

class IndexInteractionTest extends TestCase
{
    /**
     * Client instance
     *
     * @var Sigma
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
        $this->client = Sigma::create($elasticsearch);

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
        $result = $this->client->insert($index);

        $this->isInstanceOf(Index::class);

        $element = $this->client->get('bar');

        $this->assertInstanceOf(Index::class, $element);
        $this->assertEquals($element->name, 'bar');

        // Clean up created index
        $this->client->remove('bar');
    }

    /**
     * @test
     */
    public function removeIndex(): void
    {
        // Create index to be remove
        $this->client->insert(new Index('bar'));

        $response = $this->client->remove('bar');

        $this->assertTrue($response);
    }

    /**
     * @test
     */
    public function listIndices(): void
    {
        $this->client->insert(new Index('foo'));
        $this->client->insert(new Index('bar'));

        $collection = $this->client->list();

        $this->assertCount(2, $collection);
        $this->assertInstanceOf(Collection::class, $collection);
    }

    /**
     * @test
     */
    public function getIndex(): void
    {
        $this->client->insert(new Index('foo'));

        $element = $this->client->get('foo');

        $this->assertInstanceOf(Index::class, $element);
        $this->assertEquals('foo', $element->name);
    }
}
