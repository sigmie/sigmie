<?php

declare(strict_types=1);

namespace Sigmie\Index\Alias;

use Sigmie\Base\APIs\Alias as AliasAPI;
use Sigmie\Base\APIs\Index;

trait Actions
{
    use AliasAPI;
    use Index;

    protected function switchAlias(string $alias, string $from, string $to): bool
    {
        $body = ['actions' => [
            ['remove' => ['index' => $from, 'alias' => $alias]],
            ['add' => ['index' => $to, 'alias' => $alias]],
        ]];

        $res = $this->aliasAPICall('POST', $body);

        return $res->json('acknowledged');
    }

    protected function createAlias(string $index, string $alias): void
    {
        $path = sprintf('%s/_alias/%s', $index, $alias);

        $this->indexAPICall($path, 'PUT');
    }

    protected function aliasExists(string $alias): bool
    {
        $path = '_alias/'.$alias;

        $res = $this->indexAPICall($path, 'HEAD');

        return $res->code() === 200;
    }

    protected function deleteAlias(string $index, string $alias): bool
    {
        $path = sprintf('%s/_alias/%s', $index, $alias);

        $response = $this->indexAPICall($path, 'DELETE');

        return $response->json('acknowledged');
    }
}
