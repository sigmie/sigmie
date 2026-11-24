<?php

declare(strict_types=1);

namespace Sigmie\Index\Alias;

use RuntimeException;
use Sigmie\Base\APIs\Alias as AliasAPI;
use Sigmie\Base\APIs\Index;
use stdClass;

trait Actions
{
    use AliasAPI;
    use Index;

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
        return array_map('strval', array_keys($this->aliasSources($alias)));
    }

    protected function aliasSources(string $alias): array
    {
        $path = '_alias/'.$alias;
        $response = $this->indexAPICall($path, 'HEAD');

        if ($response->code() === 404) {
            return [];
        }

        if ($response->code() !== 200) {
            throw new RuntimeException("Could not resolve alias '$alias'.");
        }

        return $this->indexAPICall($path, 'GET')->json();
    }

    /**
     * Point the alias at $to only, detaching it from every index in $from in
     * the same atomic request. Required removals reject a stale source, and
     * the explicit write index prevents two first rebuilds from both winning.
     * A single destination cannot retain different filters or routing rules.
     *
     * @param  array<int, string>  $from
     */
    protected function moveAlias(
        string $alias,
        array $from,
        string $to,
        ?string $oldAlias = null,
        ?string $pendingAlias = null,
        bool $markRetired = false,
        ?string $sourceMarker = null,
        ?string $fillRouting = null,
    ): void {
        if ($oldAlias !== null && $oldAlias !== $alias && $this->aliasIndices($alias) !== []) {
            throw AliasAlreadyExists::forAlias($alias);
        }

        $sourceAlias = $oldAlias ?? $alias;
        $aliasSettings = null;
        $writeIndex = $from === [];

        if ($from !== []) {
            $response = $this->indexAPICall('_alias/'.$sourceAlias, 'GET');
            $sources = json_decode((string) $response->psr()->getBody(), flags: JSON_BIGINT_AS_STRING);

            $unexpected = array_diff(array_keys((array) $sources), $from);
            if ($unexpected !== []) {
                throw new RuntimeException("Alias '$sourceAlias' also points to '".reset($unexpected)."'.");
            }

            foreach ($from as $index) {
                $settings = (array) ($sources->{$index}->aliases->{$sourceAlias} ?? []);
                $writeIndex = $writeIndex || ($settings['is_write_index'] ?? (count($from) === 1));
                unset($settings['is_write_index']);
                if ($aliasSettings !== null && $this->comparableSettings($settings) !== $this->comparableSettings($aliasSettings)) {
                    throw new RuntimeException("Alias '$sourceAlias' has different settings on its source indices.");
                }

                $aliasSettings = $settings;
            }
        }

        if ($oldAlias === null && $pendingAlias !== null && ($aliasSettings['index_routing'] ?? null) !== $fillRouting) {
            throw new RuntimeException("Alias '$alias' index routing changed while the rebuild was being filled.");
        }

        $removes = array_map(fn (string $index): array => ['remove' => [
            'index' => $index,
            'alias' => $sourceAlias,
            'must_exist' => true,
        ]], $from);

        if ($sourceMarker !== null) {
            foreach ($from as $index) {
                $removes[] = ['remove' => ['index' => $index, 'alias' => $sourceMarker, 'must_exist' => true]];
            }
        }

        if ($pendingAlias !== null) {
            $removes[] = ['remove' => ['index' => $to, 'alias' => $pendingAlias, 'must_exist' => true]];
        }

        $this->applyAliasActions($alias, [
            ...$removes,
            ['add' => ['index' => $to, 'alias' => $alias, 'is_write_index' => $writeIndex, ...($aliasSettings ?? [])]],
            ...($markRetired ? array_map(fn (string $index): array => ['add' => [
                'index' => $index,
                'alias' => $this->retiredAlias($to),
                'is_hidden' => true,
            ]], $from) : []),
        ]);
    }

    private function comparableSettings(array $settings): string
    {
        if (($settings['is_hidden'] ?? null) === false) {
            unset($settings['is_hidden']);
        }
        if (isset($settings['search_routing'])) {
            $routes = array_unique(explode(',', $settings['search_routing']));
            sort($routes, SORT_STRING);
            $settings['search_routing'] = implode(',', $routes);
        }
        return json_encode($this->sortSettingKeys((object) $settings), JSON_THROW_ON_ERROR);
    }

    private function sortSettingKeys(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $keys = (array) $value;
            ksort($keys, SORT_STRING);

            return (object) array_map($this->sortSettingKeys(...), $keys);
        }

        return is_array($value) ? array_map($this->sortSettingKeys(...), $value) : $value;
    }

    protected function retiredAlias(string $destination): string
    {
        return '.sigmie-retired-'.hash('sha256', $destination);
    }

    protected function applyAliasActions(string $alias, array $actions): void
    {
        $response = $this->aliasAPICall('POST', ['actions' => $actions]);

        if ($response->json('acknowledged') !== true) {
            throw new RuntimeException("Alias update for '$alias' was not acknowledged.");
        }
    }

    protected function deleteAlias(string $index, string $alias): bool
    {
        $path = sprintf('%s/_alias/%s', $index, $alias);

        $response = $this->indexAPICall($path, 'DELETE');

        return $response->json('acknowledged');
    }
}
