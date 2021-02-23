<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\App\Core\Docker\Images\Elasticsearch;
use Sigmie\Base\APIs\Calls\Alias as AliasAPI;
use Sigmie\Base\Exceptions\ElasticsearchException;

trait AliasActions
{
    use AliasAPI;

    protected function createAlias(Index $index, $alias): Index
    {
        $path = "/{$index->getName()}/_alias/{$alias}";

        $this->indexAPICall($path, 'PUT');

        return $index;
    }

    protected function aliasExists(string $alias): bool
    {
        $path = "/_alias/{$alias}";

        try {
            $this->indexAPICall($path, 'HEAD');

            return true;
        } catch (ElasticsearchException) {
            return false;
        }
    }

    protected function deleteAlias(Index $index, string $alias): bool
    {
        $path = "/{$index->getName()}/_alias/{$alias}";

        $response = $this->indexAPICall($path, 'DELETE');

        return $response->json('acknowledged');
    }

    public function switchAlias(string $alias, Index $from, Index $to): bool
    {
        $body = ['actions' => [
            ['remove' => ['index' => $from->getName(), 'alias' => $alias]],
            ['add' => ['index' => $to->getName(), 'alias' => $alias]]
        ]];

        $res = $this->aliasAPICall('POST', $body);

        return $res->json('acknowledged');
    }
}
