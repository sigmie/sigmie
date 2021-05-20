<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\APIs\Calls\Alias as AliasAPI;
use Sigmie\Base\Exceptions\ElasticsearchException;

trait AliasActions
{
    use AliasAPI;

    public function switchAlias(string $alias, string $from, string $to): bool
    {
        $body = ['actions' => [
            ['remove' => ['index' => $from, 'alias' => $alias]],
            ['add' => ['index' => $to, 'alias' => $alias]]
        ]];

        $res = $this->aliasAPICall('POST', $body);

        return $res->json('acknowledged');
    }

    protected function createAlias(string $index, string $alias)
    {
        $path = "/{$index}/_alias/{$alias}";

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

    protected function deleteAlias(string $index, string $alias): bool
    {
        $path = "/{$index}/_alias/{$alias}";

        $response = $this->indexAPICall($path, 'DELETE');

        return $response->json('acknowledged');
    }
}
