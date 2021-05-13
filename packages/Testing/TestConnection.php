<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Connection;
use Sigmie\Testing\JSONClient as TestingJsonClient;

trait TestConnection
{
    use API;

    abstract protected function testId(): string;

    public function setupTestConnection()
    {
        $client = TestingJsonClient::create(getenv('ES_HOST'));

        $client->foo($this->testId());

        $this->setHttpConnection(new Connection($client));
    }
}
