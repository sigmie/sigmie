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
    use API;
    use Cat;
    use Cluster;
    use Index;
    use Ingest;
    use Script;
    use Template;

    protected function clearElasticsearch(ElasticsearchConnection $connection): void
    {
        $this->setElasticsearchConnection($connection);

        $response = $this->catAPICall('indices?expand_wildcards=all', 'GET');

        $names = array_map(fn ($data) => $data['index'], $response->json());

        // Five percent-encoded 255-byte names fit Elasticsearch's 4 KiB request-line limit.
        $nameChunks = array_chunk(array_filter($names, fn ($name): bool => ! str_starts_with($name, '.')), 5);

        // Delete indices
        foreach ($nameChunks as $chunk) {
            while ($chunk !== []) {
                try {
                    $this->indexAPICall(implode(',', $chunk).'?ignore_unavailable=true', 'DELETE');
                    break;
                } catch (ElasticsearchException $exception) {
                    $missing = array_search($exception->json('json.error.index'), $chunk, true);
                    if ($exception->getCode() !== 404 || $exception->json('type') !== 'index_not_found_exception' || $missing === false) {
                        throw $exception;
                    }

                    unset($chunk[$missing]);
                }
            }
        }

        // Delete searches
        $response = $this->clusterAPICall('state/metadata?pretty&filter_path=metadata.stored_scripts');

        $scripts = $response->json('metadata.stored_scripts');

        $names = (is_null($scripts)) ? [] : array_keys($scripts);

        foreach ($names as $name) {
            try {
                $this->scriptAPICall('DELETE', (string) $name);
            } catch (ElasticsearchException $exception) {
                if ($exception->getCode() !== 404 || $exception->json('type') !== 'resource_not_found_exception') {
                    throw $exception;
                }
            }
        }

        // Delete index templates
        $this->templateAPICall('*', 'DELETE');

        // Delete pipelines
        $this->ingestAPICall('*', 'DELETE');
    }
}
