<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\APIs\API;
use Sigmie\Base\APIs\Cat;
use Sigmie\Base\APIs\Cluster;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Ingest;
use Sigmie\Base\APIs\Script;
use Sigmie\Base\APIs\Template;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\ElasticsearchException;

trait ClearElasticsearch
{
    use Cat;
    use Index;
    use API;
    use Script;
    use Cluster;
    use Template;
    use Ingest;

    protected function clearElasticsearch(ElasticsearchConnection $connection): void
    {
        $this->setElasticsearchConnection($connection);

        try {

            $response = $this->ingestAPICall('', 'GET');

            // Delete ingest pipelines
            foreach ($response->json() as $id => $pipeline) {
                $this->ingestAPICall($id, 'DELETE');
            }
        } catch (ElasticsearchException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
        }


        $response = $this->catAPICall('indices', 'GET');

        $names = array_map(fn ($data) => $data['index'], $response->json());

        $nameChunks = array_chunk($names, 50);

        //Delete indices
        foreach ($nameChunks as $chunk) {
            $this->indexAPICall(implode(',', $chunk), 'DELETE');
        }

        // Delete searches
        $response = $this->clusterAPICall('state/metadata?pretty&filter_path=metadata.stored_scripts');

        $scripts = $response->json('metadata.stored_scripts');

        $names = (is_null($scripts)) ? [] : array_keys($scripts);

        foreach ($names as $name) {
            $this->scriptAPICall('DELETE', (string) $name);
        }

        //Delete index templates
        $this->templateAPICall('*', 'DELETE');
    }
}
