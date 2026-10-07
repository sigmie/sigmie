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

    /**
     * @return array<int, string> Physical index names the alias points to.
     */
    protected function aliasIndices(string $alias): array
    {
        if (! $this->aliasExists($alias)) {
            return [];
        }

        return array_keys($this->indexAPICall('_alias/'.$alias, 'GET')->json());
    }

    /**
     * Point the alias at $to only, detaching it from every index in $from in
     * the same atomic request, so readers never see zero or two indices.
     *
     * @param  array<int, string>  $from
     */
    protected function moveAlias(string $alias, array $from, string $to): void
    {
        $removes = array_map(fn (string $index): array => ['remove' => ['index' => $index, 'alias' => $alias]], $from);

        $this->aliasAPICall('POST', ['actions' => [
            ...$removes,
            ['add' => ['index' => $to, 'alias' => $alias]],
        ]]);
    }

    protected function deleteAlias(string $index, string $alias): bool
    {
        $path = sprintf('%s/_alias/%s', $index, $alias);

        $response = $this->indexAPICall($path, 'DELETE');

        return $response->json('acknowledged');
    }
}
