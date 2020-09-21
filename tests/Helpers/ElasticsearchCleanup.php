<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Sigmie\Search\SigmieClient;

trait ElasticsearchCleanup
{
    public function deleteAllIndices()
    {
        $client = SigmieClient::createWithoutAuth('http://es:9200');

        foreach ($client->indices()->list() as $index) {
            $client->indices()->delete($index->name);
        }
    }
}
