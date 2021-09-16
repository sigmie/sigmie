<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Connection;
use Sigmie\Http\JSONClient;

use function Sigmie\Helpers\testing_host;

trait TestConnection
{
    use API;

    public function setupTestConnection(): void
    {
        $client = JSONClient::create(testing_host());

        $this->setHttpConnection(new Connection($client));
    }
}
