<?php

declare(strict_types=1);

namespace Sigmie\Tests\Integration;

use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\Exception;
use Sigmie\Search\Cluster\Cluster;
use Sigmie\Search\Indices\Index;
use Sigmie\Search\SigmieClient;
use Sigmie\Tests\Helpers\IntegrationTestCase;

class ClientTest extends IntegrationTestCase
{
    /**
     * @var SigmieClient
     */
    private $sigmieClient;

    public function setUp(): void
    {
        $this->sigmieClient = SigmieClient::createWithoutAuth('http://' . getenv('ES_HOST') . ':' . getenv('ES_PORT'));
    }

    /**
     * @test
     */
    public function client_create_index()
    {
        $index = $this->sigmieClient->indices()->create('foo');

        $this->assertInstanceOf(Index::class, $index);
    }

    /**
     * @test
     */
    public function cluster_instance()
    {
        $index = $this->sigmieClient->cluster()->get();

        $this->assertInstanceOf(Cluster::class, $index);
    }
}
