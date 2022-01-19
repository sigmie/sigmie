<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use App\Helpers\ProxyCert;
use Sigmie\Base\APIs\Cat;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Connection;
use Sigmie\Http\JSONClient;

trait ClearIndices
{
    use TestConnection;
    use Cat;
    use Index;
    use API;

    protected function clearIndices(?string $url = null): void
    {
        if (is_null($url)) {
            $this->setupTestConnection();
        } else {
            $client = JSONClient::create($url, new ProxyCert());

            $this->setHttpConnection(new Connection($client));
        }

        $response = $this->catAPICall('/indices', 'GET', );

        $names = array_map(fn ($data) => $data['index'], $response->json());

        $nameChunks = array_chunk($names, 50);

        foreach ($nameChunks as $chunk) {
            $this->indexAPICall(implode(',', $chunk), 'DELETE');
        }
    }
}
