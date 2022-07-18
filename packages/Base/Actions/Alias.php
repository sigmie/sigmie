<?php

declare(strict_types=1);

namespace Sigmie\Base\Actions;

use Sigmie\Base\APIs\Alias as AliasAPI;
use Sigmie\Base\APIs\Index;

trait Alias
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
        $path = "{$index}/_alias/{$alias}";

        $this->indexAPICall($path, 'PUT');
    }

    protected function aliasExists(string $alias): bool
    {
        $path = "_alias/{$alias}";

        $res = $this->indexAPICall($path, 'HEAD');

        return $res->code() === 200;
    }

    protected function deleteAlias(string $index, string $alias): bool
    {
        $path = "{$index}/_alias/{$alias}";

        $response = $this->indexAPICall($path, 'DELETE');

        return $response->json('acknowledged');
    }
}
