<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\APIs\Cat;
use Sigmie\Base\APIs\Cluster;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Script;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Connection;
use Sigmie\Http\Contracts\Auth;
use Sigmie\Http\JSONClient;

trait ClearIndices
{
    use Cat;
    use Index;
    use API;
    use Script;
    use Cluster;

    protected function clearIndices(string $url, Auth $auth = null): void
    {
        $client = JSONClient::create($url, $auth);

        $this->setHttpConnection(new Connection($client));

        $response = $this->catAPICall('indices', 'GET', );

        $names = array_map(fn ($data) => $data['index'], $response->json());

        $nameChunks = array_chunk($names, 50);

        foreach ($nameChunks as $chunk) {
            $this->indexAPICall(implode(',', $chunk), 'DELETE');
        }

        $response = $this->clusterAPICall('state/metadata?pretty&filter_path=metadata.stored_scripts');

        $scripts = $response->json('metadata.stored_scripts');

        $names = (is_null($scripts)) ? [] : array_keys($scripts);

        foreach ($names as $name) {
            $this->scriptAPICall('DELETE', (string)$name);
        }
    }
}
