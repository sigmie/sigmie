<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\APIs\Calls\Alias as AliasAPI;

trait AliasActions
{
    protected function createAlias(Index $index): Index
    {
        $alias = $index->getAlias();

        $path = "/{$index->getName()}/_alias/{$alias}";

        $this->indexAPICall($path, 'PUT');

        return $index;
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
