<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Connection;
use Sigmie\Http\JSONClient;

trait TestConnection
{
    use API;

    public function setupTestConnection(): void
    {
        $client = JSONClient::create(getenv('ES_HOST'));

        $this->setHttpConnection(new Connection($client));
    }
}
