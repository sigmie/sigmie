<?php

declare(strict_types=1);

namespace Sigmie\Document;

use Sigmie\Base\APIs\Bulk as BulkAPI;
use Sigmie\Base\APIs\Delete as DeleteAPI;
use Sigmie\Base\APIs\Doc as DocAPI;
use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\APIs\Mget as MgetAPI;
use Sigmie\Base\APIs\Search as SearchAPI;
use Sigmie\Base\APIs\Update as UpdateAPI;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\NdJSONRequest;
use Sigmie\Base\Http\Responses\Bulk;
use Sigmie\Document\Document as Doc;
use Sigmie\Mappings\Properties;
use Sigmie\Shared\Collection;
use stdClass;

trait Actions
{
    use BulkAPI;
    use DeleteAPI;
    use DocAPI;
    use IndexAPI;
    use MgetAPI;
    use SearchAPI;
    use UpdateAPI;

    protected function writeDocument(string $indexName, Doc $doc, string $refresh, bool $replace, Properties $properties): void
    {
        $action = $replace && isset($doc->_id) ? 'index' : 'create';
        $metadata = isset($doc->_id) ? ['_id' => $doc->_id] : (object) [];
        $body = [[$action => $metadata], $properties->sourceObject($doc->_source)];

        Bulk::assertOperationsSucceeded($this->writeDocuments($indexName, $body, [$doc], $refresh));
    }

    protected function upsertDocuments(string $indexName, array $documents, string $refresh, Properties $properties, array $snapshots = []): void
    {
        $targetBytes = 5 * 1024 * 1024;
        $body = [];
        $batch = [];
        $bytes = 0;
        $items = [];

        foreach ($documents as $document) {
            $source = $properties->sourceObject($document->_source);

            $id = $document->_id ?? null;
            $snapshot = $id !== null ? ($snapshots[$id] ?? null) : null;
            $operation = match (true) {
                $snapshot !== null && ($snapshot['found'] ?? false) => [
                    ['update' => [
                        '_id' => $id,
                        'if_seq_no' => $snapshot['_seq_no'],
                        'if_primary_term' => $snapshot['_primary_term'],
                    ]],
                    ['script' => [
                        'source' => <<<'PAINLESS'
                            if (ctx._index != params.index) {
                                throw new IllegalStateException('Write index changed during patch');
                            }
                            if (params.changes.isEmpty()) {
                                ctx.op = 'noop';
                            }
                            for (def change : params.changes) {
                                def target = ctx._source;
                                def path = change.path;
                                for (int i = 0; i < path.size() - 1; ++i) {
                                    target = target.get(path[i]);
                                }
                                def key = path[path.size() - 1];
                                if (change.containsKey('value')) {
                                    target.put(key, change.value);
                                } else {
                                    target.remove(key);
                                }
                            }
                            PAINLESS,
                        'params' => [
                            'index' => $snapshot['_index'],
                            'changes' => $this->sourceChanges($snapshot['_source'], (array) $source),
                        ],
                    ]],
                ],
                $id === null => [['create' => (object) []], $source],
                $snapshot !== null => [['create' => ['_id' => $id]], $source],
                default => [['update' => ['_id' => $id]], ['doc' => $source, 'doc_as_upsert' => true]],
            };
            $size = strlen(NdJSONRequest::encode($operation));

            if ($batch !== [] && $bytes + $size > $targetBytes) {
                array_push($items, ...$this->writeDocuments($indexName, $body, $batch, $refresh));
                $body = [];
                $batch = [];
                $bytes = 0;
            }

            array_push($body, ...$operation);
            $batch[] = $document;
            $bytes += $size;
        }

        if ($batch !== []) {
            array_push($items, ...$this->writeDocuments($indexName, $body, $batch, $refresh));
        }

        Bulk::assertOperationsSucceeded($items);
    }

    private function sourceChanges(array $before, array $after, array $parent = []): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            $path = [...$parent, (string) $key];
            $previous = $before[$key] ?? null;
            if (($value instanceof stdClass || (is_array($value) && ! array_is_list($value)))
                && ($previous instanceof stdClass || (is_array($previous) && ! array_is_list($previous)))) {
                array_push($changes, ...$this->sourceChanges((array) $previous, (array) $value, $path));
            } elseif ($previous !== $value || ! array_key_exists($key, $before)) {
                $changes[] = ['path' => $path, 'value' => $value];
            }
        }
        foreach (array_diff_key($before, $after) as $key => $value) {
            $changes[] = ['path' => [...$parent, (string) $key]];
        }

        return $changes;
    }

    private function writeDocuments(string $indexName, array $body, array $documents, string $refresh): array
    {
        try {
            $items = $this->bulkAPICall($indexName, $body, $refresh)->items();
        } catch (ElasticsearchException $exception) {
            if ($exception->getCode() !== 413 || count($documents) < 2) {
                throw $exception;
            }

            // Each write has two NDJSON lines. Keep the operation and source together.
            $middle = intdiv(count($documents), 2);
            $middleLine = $middle * 2;

            return [
                ...$this->writeDocuments($indexName, array_slice($body, 0, $middleLine), array_slice($documents, 0, $middle), $refresh),
                ...$this->writeDocuments($indexName, array_slice($body, $middleLine), array_slice($documents, $middle), $refresh),
            ];
        }

        foreach ($documents as $position => $doc) {
            $item = reset($items[$position]);

            if ($item['status'] >= 400 || isset($item['error'])) {
                continue;
            }

            if (! isset($doc->_id)) {
                $doc->id($item['_id']);
            }

            $doc->index($item['_index']);
        }

        return $items;
    }

    protected function documentExists(string $indexName, string $_id): bool
    {
        if ($_id === '.' || $_id === '..') {
            return $this->retrieveDocuments($indexName, [$_id])->first() !== null;
        }

        $res = $this->docAPICall($indexName, $_id, 'HEAD');

        return $res->code() === 200;
    }

    protected function readPatchSources(string $indexName, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $body = ['ids' => array_values($ids)];
        $items = $this->mgetAPICall($indexName, $body)->json('docs') ?? [];
        $firstError = $items[0]['error'] ?? [];
        if (($firstError['type'] ?? null) === 'illegal_argument_exception'
            && str_contains($firstError['reason'] ?? '', 'has more than one index associated with it')) {
            foreach ($this->indexAPICall('_alias/'.$indexName, 'GET')->json() as $index => $definition) {
                $alias = $definition['aliases'][$indexName] ?? [];
                if (($alias['is_write_index'] ?? false) === true) {
                    $routing = $alias['index_routing'] ?? $alias['routing'] ?? null;
                    $items = $this->mgetAPICall((string) $index, $body, $routing === null ? [] : ['routing' => $routing])->json('docs') ?? [];

                    break;
                }
            }
        }

        $snapshots = [];
        foreach ($items as $item) {
            $error = $item['error'] ?? null;
            if ($error !== null) {
                if (! $this->requireAlias && ($error['type'] ?? null) === 'index_not_found_exception') {
                    continue;
                }

                throw new ElasticsearchException($error, $item['status'] ?? 0);
            }

            if (($item['found'] ?? false) && ! isset($item['_source'])) {
                throw new ElasticsearchException([
                    'type' => 'illegal_argument_exception',
                    'reason' => sprintf("Cannot patch document '%s' without _source.", $item['_id']),
                ], 400);
            }

            $snapshots[$item['_id']] = $item;
        }

        return $snapshots;
    }

    protected function retrieveDocuments(
        string $indexName,
        array $ids,
    ): Collection {
        if ($ids === []) {
            return new Collection;
        }

        $filters = array_filter(['includes' => $this->only, 'excludes' => $this->except]);
        $options = $filters === [] ? [] : ['_source' => $filters];

        $response = $this->mgetAPICall($indexName, [
            'docs' => array_map(fn (string $id): array => ['_id' => $id, ...$options], array_values($ids)),
        ]);

        return new Collection($response->docs());
    }

    protected function deleteDocument(string $indexName, string $identifier, string $refresh): bool
    {
        if ($identifier === '.' || $identifier === '..') {
            return $this->deleteDocuments($indexName, [$identifier], $refresh);
        }

        $response = $this->deleteAPICall(
            $indexName,
            $identifier,
            $refresh
        );

        return $response->code() !== 404 && $response->json('result') === 'deleted';
    }

    protected function deleteDocuments(string $indexName, array $ids, string $refresh): bool
    {
        if ($ids === []) {
            return true;
        }

        $body = [];
        foreach ($ids as $id) {
            $body = [
                ...$body,
                ['delete' => ['_index' => $indexName, '_id' => $id]],
            ];
        }

        $response = $this->bulkAPICall($indexName, $body, $refresh);

        $response->assertItemsSucceeded();

        foreach ($response->items() as $item) {
            if (($item['delete']['status'] ?? 0) >= 400 || ($item['delete']['result'] ?? null) !== 'deleted') {
                return false;
            }
        }

        return true;
    }
}
