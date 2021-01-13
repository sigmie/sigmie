<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Connection;
use Sigmie\Http\JsonClient;

trait TestConnection
{
    use API;

    public function setupTestConnection()
    {
        $this->setConnection(new Connection(JsonClient::create(getenv('ES_HOST'))));
    }
}
