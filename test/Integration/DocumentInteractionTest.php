<?php

namespace Sigma\Test\Integration;

use Elasticsearch\ClientBuilder;
use Sigma\Client;
use Sigma\Collection;
use Sigma\Index\Index;
use PHPUnit\Framework\TestCase;

class DocumentInteractionTest extends TestCase
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
    public function addDocument(): void
    {
        $index = new Index('bar');
        $result = $this->client->index()->insert($index);

        $this->client->boot($index);
    }
}
