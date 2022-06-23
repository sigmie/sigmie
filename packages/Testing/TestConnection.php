<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use App\Helpers\ProxyCert;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Connection;
use Sigmie\Http\JSONClient;

use function Sigmie\Helpers\testing_host;

trait TestConnection
{
    use API;

    public function setupTestConnection(): void
    {
        $token = '1';

        if (getenv('PARATEST') !== false) {
            $token = (string) getenv('TEST_TOKEN');
        }

        $host = testing_host($token);

        $client = JSONClient::create($host, new ProxyCert());

        $this->setHttpConnection(new Connection($client));
    }
}
