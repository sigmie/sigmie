<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Connection;
use function Sigmie\Helpers\testing_host;

use Sigmie\Http\JSONClient;

trait TestConnection
{
    use API;

    public function setupTestConnection(): void
    {
        $client = JSONClient::create(testing_host());

        $this->setHttpConnection(new Connection($client));
    }
}
