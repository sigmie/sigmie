<?php

declare(strict_types=1);

namespace Sigmie\Support\Tests;

use Sigmie\Base\Http\Connection;
use Sigmie\Http\JSONClient;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class SigmieTest extends TestCase
{
    /**
     * @test
     */
    public function is_connected_returns_false_on_timeout()
    {
        $client = JSONClient::create('http://demo:9200/');
        $connection = new Connection($client);
        $sigmie = new Sigmie($connection);

        $this->assertFalse($sigmie->isConnected());
    }

    /**
     * @test
     */
    public function is_connected_returns_true_when_connected()
    {
        $this->assertTrue($this->sigmie->isConnected());
    }
}
