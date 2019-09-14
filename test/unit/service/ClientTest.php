<?php

use niElastic\Service\Client;

use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    protected function setUp(): void
    {
        $this->client = new Client();
    }

    /**
     * @test
     */
    public function clientDefaultHost(): void
    {
        $this->assertEquals($this->client->gethost(), '127.0.0.1');
    }

    /**
     * @test
     */
    public function clientDefaultPort(): void
    {
        $this->assertEquals($this->client->getPort(), '9200');
    }
}
