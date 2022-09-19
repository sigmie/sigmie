<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Connection;
use Sigmie\Http\JSONClient;

use function App\Helpers\testing_host;

trait HasTestConnection
{
    use API;

    public function setupTestConnection(): void
    {
        $token = '1';

        if (getenv('PARATEST') !== false) {
            $token = (string) getenv('TEST_TOKEN');
        }

        $client = JSONClient::create(testing_host($token));

        $this->setHttpConnection(new Connection($client));
    }
}
