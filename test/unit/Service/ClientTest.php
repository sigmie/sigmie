<?php

namespace Ni\Elastic\Test\Service;

use Ni\Elastic\Service\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    /**
     * @test
     */
    public function clientDefaultHost(): void
    {
        $client = new Client();
        $this->assertEquals($client->gethost(), '127.0.0.1');
    }

    /**
     * @test
     */
    public function clientDefaultPort(): void
    {
        $client = new Client();
        $this->assertEquals($client->getPort(), '9200');
    }
}
