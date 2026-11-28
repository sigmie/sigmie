<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use RuntimeException;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Contracts\CollectionHook;
use Sigmie\Document\Document;
use Sigmie\Index\Alias\AliasAlreadyExists;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\IndexRebuild;
use Sigmie\Index\IndexUpdateTask;
use Sigmie\Index\NewIndex;
use Sigmie\Index\RebuildLost;
use Sigmie\Index\RebuildTooSmall;
use Sigmie\Index\UpdateIndex as Update;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;
use stdClass;

class IndexUpdateTest extends TestCase
{
    public function test_update_and_rebuild_get_a_new_index_while_the_clock_is_frozen(): void
    {
        $alias = uniqid('frozen_');
        $props = new NewProperties;
        $props->title('name');

        Carbon::setTestNow('2026-01-01 10:00:00');
        try {
            $created = $this->sigmie->newIndex($alias)->properties($props)->create();
            $this->sigmie->collect($alias, refresh: true)->properties($props)->merge([new Document(['name' => 'Lamp'], _id: 'lamp')]);

            $updated = $created->update(fn (NewIndex $index): NewIndex => $index->properties($props));
            $rebuilt = $this->sigmie->newIndex($alias)->properties($props)
                ->rebuild(fn (AliveCollection $documents): AliveCollection => $documents->properties($props)->merge([new Document(['name' => 'Chair'], _id: 'chair')]));
        } finally {
            Carbon::setTestNow();
        }

        $this->assertNotSame($created->name, $updated->name);
        $this->assertNotSame($updated->name, $rebuilt->name);
        $this->assertSame(['chair'], array_keys($this->sigmie->collect($alias)->toArray()));
        $this->assertSame([$rebuilt->name], array_map(fn ($index): string => $index->name, $this->sigmie->indices($alias.'_*')));
    }

    public function test_an_unfinished_update_exclusively_owns_the_source_write_block(): void
    {
        $alias = '.sigmie-test-'.bin2hex(random_bytes(8));
        $index = $this->sigmie->newIndex($alias)->replicas(0)->create();
        $configure = fn (Update $update): Update => $update->replicas(0);
        $task = $index->asyncUpdate($configure);

        try {
            $error = null;
            try {
                $index->asyncUpdate($configure);
            } catch (RuntimeException $exception) {
                $error = $exception;
            }

            $this->assertNotNull($error, 'Both copies started.');

            $task->waitAndFinish();
            $this->assertSame($task->dest, $this->sigmie->index($alias)->name);
        } finally {
            foreach (array_keys($this->indexAPICall($alias.'_*?expand_wildcards=all', 'GET')->json()) as $physical) {
                $this->indexAPICall($physical, 'DELETE');
            }
        }
    }

    public function test_a_recreated_source_cannot_be_retired_by_its_predecessors_task(): void
    {
        $alias = '.sigmie-test-'.bin2hex(random_bytes(8));
        $source = $this->sigmie->newIndex($alias)->replicas(0)->create();
        $task = $source->asyncUpdate(fn (Update $update): Update => $update->replicas(0));

        try {
            $status = $this->indexAPICall('_tasks/'.$task->pack()['task'].'?wait_for_completion=true&timeout=30s', 'GET');
            $this->assertTrue($status->json('completed'));
            $source->delete();
            $this->indexAPICall($source->name, 'PUT', [
                'settings' => ['number_of_replicas' => 0],
                'aliases' => [$alias => ['is_write_index' => true]],
            ]);

            try {
                $task->finish();
                $this->fail('The task promoted a copy of the deleted source.');
            } catch (ElasticsearchException $exception) {
                $this->assertSame('aliases_not_found_exception', $exception->json('type'));
            }

            $replacement = $this->sigmie->index($alias);
            $this->assertSame($source->name, $replacement->name);
            $updated = $replacement->update(fn (Update $update): Update => $update->replicas(0));
            $this->assertSame($updated->name, $this->sigmie->index($alias)->name);
        } finally {
            foreach (array_keys($this->indexAPICall($alias.'_*?expand_wildcards=all', 'GET')->json()) as $physical) {
                $this->indexAPICall($physical, 'DELETE');
            }
        }
    }

    public function test_lifecycle_sync_update_rejects_a_superseded_destination(): void
    {
        $connection = $this->offlineConnection(fn ($method, $path): array => match (sprintf('%s %s', $method, $path)) {
            'PUT /source/_block/write' => ['shards_acknowledged' => true],
            'POST /_reindex' => ['failures' => [], 'timed_out' => false],
            'GET /_alias/movies' => ['source' => ['aliases' => ['movies' => []]]],
            'GET /movies' => ['other' => ['aliases' => ['movies' => []], 'settings' => [], 'mappings' => []]],
            default => ['acknowledged' => true],
        });
        $index = new AliasedIndex('source', 'movies');
        $index->setElasticsearchConnection($connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Alias 'movies' no longer points to");
        $index->update(fn (Update $update): Update => $update);
    }

    public function test_lifecycle_update_rejects_disabled_source_before_allocating_a_destination(): void
    {
        $connection = $this->offlineConnection(function (string $method, string $path): array {
            $this->assertSame('GET /source', sprintf('%s %s', $method, $path));

            return ['source' => ['aliases' => ['movies' => []], 'mappings' => ['_source' => ['enabled' => false]]]];
        });
        $index = new AliasedIndex('source', 'movies');
        $index->setElasticsearchConnection($connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot copy every indexed field');
        $index->update(fn (Update $update): Update => $update->replicas(0));
    }

    public function test_lifecycle_update_preserves_the_reindex_error_when_unblocking_also_fails(): void
    {
        $reindexError = new ElasticsearchException(['type' => 'search_phase_execution_exception'], 500);
        $connection = $this->offlineConnection(fn (string $method, string $path): array => match (sprintf('%s %s', $method, $path)) {
            'PUT /source/_block/write' => ['shards_acknowledged' => true],
            'POST /_reindex' => throw $reindexError,
            'PUT /source/_settings' => throw new ElasticsearchException(['type' => 'cluster_block_exception'], 403),
            default => ['acknowledged' => true],
        });
        $index = new AliasedIndex('source', 'movies');
        $index->setElasticsearchConnection($connection);

        try {
            $index->update(fn (Update $update): Update => $update->replicas(0));
            $this->fail('The update failed.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame($reindexError, $runtimeException->getPrevious());
            $this->assertStringContainsString('cluster_block_exception', $runtimeException->getMessage());
        }
    }

    /** @dataProvider preexistingWriteBlockUpdateModes */
    public function test_lifecycle_update_does_not_release_a_preexisting_write_block(string $mode): void
    {
        $blocked = true;
        $connection = $this->offlineConnection(function ($method, $path, array $body) use ($mode, &$blocked): array|PsrResponse {
            return match (sprintf('%s %s', $method, $path)) {
                'GET /source' => ['source' => [
                    'aliases' => ['movies' => []],
                    'settings' => ['index' => ['blocks' => ['write' => 'true']]],
                ]],
                'PUT /source/_block/write' => ['shards_acknowledged' => true],
                'PUT /source/_settings' => ['acknowledged' => ! ($blocked = $body['index']['blocks.write'])],
                'HEAD /_alias/movies' => new PsrResponse(404),
                'POST /_reindex' => $mode === 'async polling'
                    ? ['task' => 'node:1']
                    : throw new ElasticsearchException(['type' => 'mapper_parsing_exception'], 400),
                'GET /_tasks/node:1' => ['completed' => true, 'response' => [
                    'timed_out' => false,
                    'failures' => [['status' => 400, 'cause' => ['type' => 'mapper_parsing_exception']]],
                ]],
                default => ['acknowledged' => true],
            };
        });
        $index = new AliasedIndex('source', 'movies');
        $index->setElasticsearchConnection($connection);

        $configure = fn (Update $update): Update => $update;

        try {
            match ($mode) {
                'sync' => $index->update($configure),
                'async submission' => $index->asyncUpdate($configure),
                'async polling' => IndexUpdateTask::unpack($connection, $index->asyncUpdate($configure)->pack())->finish(),
            };
            $this->fail('Expected the reindex error.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(400, $elasticsearchException->getCode());
            $this->assertTrue($blocked);
        }
    }

    public static function preexistingWriteBlockUpdateModes(): array
    {
        return [['sync'], ['async submission'], ['async polling']];
    }

    public function test_lifecycle_an_existing_write_block_is_acknowledged(): void
    {
        $connection = $this->offlineConnection(fn ($method, $path): array => match (sprintf('%s %s', $method, $path)) {
            'PUT /source/_block/write' => ['acknowledged' => true, 'shards_acknowledged' => false, 'indices' => []],
            default => throw new RuntimeException(sprintf('Unexpected request %s %s', $method, $path)),
        });
        $source = new AliasedIndex('source', 'movies');
        $source->setElasticsearchConnection($connection);

        $this->expectNotToPerformAssertions();
        $source->disableWrite();
    }

    /** @dataProvider finalPipelineUpdateModes */
    public function test_lifecycle_update_defers_the_final_ingest_pipeline_until_cutover(bool $async, ?string $setting): void
    {
        $pipeline = null;
        $pipelineAtCopy = null;
        $pipelineAtCutover = null;
        $live = 'source';
        $destination = null;
        $connection = $this->offlineConnection(function ($method, $path, $body) use ($async, &$pipeline, &$pipelineAtCopy, &$pipelineAtCutover, &$live, &$destination): array {
            if ($method === 'PUT' && isset($body['mappings'])) {
                $destination = ltrim($path, '/');
                $pipeline = $body['settings']['final_pipeline'] ?? $body['settings']['index.final_pipeline'] ?? 'append-label';
            }

            $result = ['failures' => [], 'timed_out' => false];
            if ($path === '/_reindex') {
                $pipelineAtCopy = $pipeline;

                return $async ? ['task' => 'node:1'] : $result;
            }

            if ($path === '/'.$destination.'/_settings') {
                if ($method === 'GET') {
                    return [$destination => ['settings' => ['index' => ['final_pipeline' => $pipeline]]]];
                }

                $pipeline = $body['final_pipeline'] ?? $pipeline;
            }

            if ($path === '/_aliases') {
                $pipelineAtCutover = $pipeline;
                $live = $destination;
            }

            return match (sprintf('%s %s', $method, $path)) {
                'GET /_tasks/node:1' => ['completed' => true, 'response' => $result],
                'PUT /source/_block/write' => ['shards_acknowledged' => true],
                'GET /movies', 'GET /_alias/movies' => [$live => ['aliases' => ['movies' => []], 'settings' => [], 'mappings' => []]],
                default => ['acknowledged' => true],
            };
        });
        $index = new AliasedIndex('source', 'movies');
        $index->setElasticsearchConnection($connection);

        $configure = fn (Update $update): Update => $setting === null ? $update : $update->config($setting, 'append-label');

        $updated = $async
            ? IndexUpdateTask::unpack($connection, $index->asyncUpdate($configure)->pack())->finish()
            : $index->update($configure);

        $this->assertSame('_none', $pipelineAtCopy);
        $this->assertSame('append-label', $pipelineAtCutover);
        $this->assertSame($destination, $updated->name);
    }

    public static function finalPipelineUpdateModes(): array
    {
        return [[false, 'final_pipeline'], [true, 'index.final_pipeline'], [true, null]];
    }

    public function test_lifecycle_rebuild_reports_the_fill_error_if_abort_fails(): void
    {
        $connection = $this->offlineConnection(fn ($method, $path): PsrResponse|array => match (sprintf('%s %s', $method, $path)) {
            'HEAD /_alias/movies', 'HEAD /movies' => new PsrResponse(404),
            'POST /_aliases' => throw new ElasticsearchException(['type' => 'cluster_block_exception', 'reason' => 'abort blocked'], 403),
            default => ['acknowledged' => true],
        });
        $fillError = new RuntimeException('fill failed');
        $builder = (new NewIndex($connection))->alias('movies');

        try {
            $builder->rebuild(fn () => throw $fillError);
            $this->fail('Expected rebuild to fail.');
        } catch (RuntimeException|ElasticsearchException $error) {
            $this->assertSame($fillError, $error->getPrevious());
            $this->assertStringContainsString('abort blocked', $error->getMessage());
        }
    }

    public function test_lifecycle_rebuild_collection_requires_the_pending_alias_for_writes(): void
    {
        $bulkUri = null;
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(
            function (ElasticsearchRequest $request) use (&$bulkUri): ElasticsearchResponse {
                $bulkUri = (string) $request->getUri();

                return $request->response(new PsrResponse(200, [], '{"errors":false,"items":[{"index":{"status":201,"_id":"1","_index":"dest"}}]}'));
            },
        );
        $pending = new IndexRebuild($connection, 'movies', 'dest', 0);
        $pending->collect()->merge([new Document(['title' => 'Film'], _id: '1')]);

        $this->assertStringStartsWith('/'.IndexRebuild::pendingAlias('dest').'/_bulk?', $bulkUri);
        $this->assertStringContainsString('require_alias=true', $bulkUri);
    }

    public function test_lifecycle_sync_cleanup_retry_cannot_delete_a_replacement_index(): void
    {
        $destination = null;
        $deletions = 0;
        $connection = $this->offlineConnection(function ($method, $path, $body) use (&$destination, &$deletions): array|PsrResponse {
            if ($method === 'PUT' && isset($body['mappings'])) {
                $destination = ltrim($path, '/');
            }

            if ($method === 'HEAD' && str_starts_with($path, '/source/_alias/')) {
                return new PsrResponse(404);
            }

            return match (sprintf('%s %s', $method, $path)) {
                'GET /_alias/movies' => ['source' => ['aliases' => ['movies' => []]]],
                'GET /movies' => [$destination => ['aliases' => ['movies' => []], 'mappings' => [], 'settings' => []]],
                'PUT /source/_block/write' => ['shards_acknowledged' => true],
                'POST /_reindex' => ['failures' => [], 'timed_out' => false],
                // The first deletion took effect despite the missing acknowledgement.
                'DELETE /source' => ['acknowledged' => ++$deletions > 1],
                default => ['acknowledged' => true],
            };
        });
        $index = new AliasedIndex('source', 'movies');
        $index->setElasticsearchConnection($connection);

        $configure = fn (Update $update): Update => $update;

        try {
            $index->update($configure);
            $this->fail('Expected the deletion to be unacknowledged.');
        } catch (RuntimeException $runtimeException) {
            $this->assertStringContainsString('Deletion', $runtimeException->getMessage());
        }

        $this->assertSame($destination, $index->update($configure)->name);
        $this->assertSame(1, $deletions);
    }

    public function test_lifecycle_rebuild_compares_numeric_index_names_as_strings(): void
    {
        $sources = ['1' => ['aliases' => ['movies' => []]], '01' => ['aliases' => ['movies' => []]]];
        $connection = $this->offlineConnection(fn ($method, $path): array => match (sprintf('%s %s', $method, $path)) {
            'GET /_alias/movies' => $sources,
            'GET /dest/_count' => ['count' => 0],
            'GET /movies' => ['dest' => ['aliases' => ['movies' => []], 'mappings' => [], 'settings' => []]],
            default => ['acknowledged' => true],
        });
        $pending = new IndexRebuild($connection, 'movies', 'dest', 0, startedOn: ['01', '1'], refresh: '1s');

        $this->assertSame('dest', $pending->finish(0)->name);
    }

    public function test_rebuild_accepts_equivalent_search_routing_on_multiple_sources(): void
    {
        $alias = uniqid('.audit6-routing-set-');
        $sources = [$alias.'_one', $alias.'_two'];
        foreach (array_combine($sources, ['A,B', 'B,A']) as $index => $routing) {
            $settings = ['search_routing' => $routing];
            if ($index === $sources[1]) {
                $settings['is_hidden'] = false;
            }

            $this->indexAPICall($index, 'PUT', ['aliases' => [$alias => $settings]]);
        }

        $pending = $this->sigmie->newIndex($alias)->replicas(0)->startRebuild();

        try {
            $index = $pending->finish(0);
            $routing = $index->raw['aliases'][$alias]['search_routing'];
            $this->assertEqualsCanonicalizing(['A', 'B'], explode(',', $routing));
        } finally {
            foreach ([...$sources, $pending->name] as $name) {
                if ($this->indexAPICall($name, 'HEAD')->code() === 200) {
                    $this->indexAPICall($name, 'DELETE');
                }
            }
        }
    }

    public function test_rebuild_counts_documents_before_enabling_a_read_block(): void
    {
        $alias = uniqid('.audit6-read-block-');
        $pending = $this->sigmie->newIndex($alias)->replicas(0)
            ->config('index', ['blocks' => ['read' => true]])->startRebuild();

        try {
            $pending->collect()->merge([new Document(['title' => 'Film'])]);
            $index = $this->sigmie->rebuilding($alias)->finish();
            $this->assertSame('true', $index->get('settings.index.blocks.read'));
        } finally {
            $this->indexAPICall($pending->name, 'DELETE');
        }
    }

    /** @dataProvider writeBlockedBuilds */
    public function test_write_block_applies_after_documents_are_copied(string $operation): void
    {
        $alias = uniqid('.audit6-write-block-');
        $source = $this->sigmie->newIndex($alias)->replicas(0)->create();
        $this->indexAPICall($alias.'/_doc/1', 'PUT', ['title' => 'Film']);
        $configure = fn (NewIndex $builder): NewIndex => $builder->replicas(0)->config('index.blocks.write', true);

        try {
            if ($operation === 'rebuild') {
                $configure($this->sigmie->newIndex($alias))->startRebuild();
                $pending = $this->sigmie->rebuilding($alias);
                $pending->collect()->merge([new Document(['title' => 'Film'], _id: '1')]);
                $pending->finish();
            } elseif ($operation === 'async') {
                $task = $source->asyncUpdate($configure);
                IndexUpdateTask::unpack($this->elasticsearchConnection, $task->pack())->waitAndFinish();
            } else {
                $source->update($configure);
            }

            $this->assertSame(1, $this->sigmie->collect($alias)->count());
            $this->assertSame('true', $this->sigmie->index($alias)->get('settings.index.blocks.write'));
        } finally {
            $this->sigmie->index($alias)?->delete();
        }
    }

    /** @dataProvider writeBlockedBuilds */
    public function test_read_only_block_applies_after_the_alias_moves(string $operation): void
    {
        $alias = uniqid('.audit6-read-only-');
        $source = $this->sigmie->newIndex($alias)->replicas(0)->create();
        $this->indexAPICall($alias.'/_doc/1', 'PUT', ['title' => 'Film']);
        $configure = fn (NewIndex $builder): NewIndex => $builder->replicas(0)->config('index.blocks.read_only', true);

        try {
            if ($operation === 'rebuild') {
                $configure($this->sigmie->newIndex($alias))
                    ->rebuild(fn (AliveCollection $collection): AliveCollection => $collection->merge([new Document(['title' => 'Film'], _id: '1')]));
            } elseif ($operation === 'async') {
                $task = $source->asyncUpdate($configure);
                IndexUpdateTask::unpack($this->elasticsearchConnection, $task->pack())->waitAndFinish();
            } else {
                $source->update($configure);
            }

            $this->assertSame(1, $this->sigmie->collect($alias)->count());
            $index = $this->sigmie->index($alias);
            $this->assertSame('true', $index->get('settings.index.blocks.read_only'));
            $this->assertSame([$index->name], array_keys($this->indexAPICall(NewIndex::physicalNamePrefix($alias).'_*?expand_wildcards=all', 'GET')->json()));
        } finally {
            foreach (array_keys($this->indexAPICall(NewIndex::physicalNamePrefix($alias).'_*?expand_wildcards=all', 'GET')->json()) as $name) {
                $this->indexAPICall($name.'/_settings', 'PUT', ['index.blocks.read_only' => false]);
                $this->indexAPICall($name, 'DELETE');
            }
        }
    }

    public static function writeBlockedBuilds(): array
    {
        return [['update'], ['async'], ['rebuild']];
    }

    public function test_resumed_rebuild_rejects_changed_index_routing(): void
    {
        $alias = uniqid('.sigmie-routing-');
        $source = $this->sigmie->newIndex($alias)->replicas(0)->create();
        $this->indexAPICall($source->name.'/_alias/'.$alias, 'PUT', ['index_routing' => 'first']);
        $pending = $this->sigmie->newIndex($alias)->replicas(0)->shards(2)->startRebuild();

        try {
            $pending->collect()->merge([new Document(['title' => 'Film'], _id: '1')]);
            $this->indexAPICall($source->name.'/_alias/'.$alias, 'PUT', ['index_routing' => 'second']);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('index routing changed');
            $this->sigmie->rebuilding($alias)->finish();
        } finally {
            $pending->abort();
            $this->sigmie->index($alias)?->delete();
        }
    }

    public function test_routed_rebuild_writes_documents_with_the_live_alias_routing(): void
    {
        $alias = uniqid('.audit6-routing-');
        $source = $this->sigmie->newIndex($alias)->replicas(0)->create();
        $this->indexAPICall($source->name.'/_alias/'.$alias, 'PUT', ['routing' => 'tenant']);

        try {
            $this->sigmie->newIndex($alias)->replicas(0)->shards(2)->startRebuild();
            $pending = $this->sigmie->rebuilding($alias);
            $pending->collect()->merge([new Document(['title' => 'Film'], _id: '1')]);
            $index = $pending->finish();

            $this->assertSame('tenant', $this->indexAPICall($index->name.'/_search', 'GET')->json('hits.hits.0._routing'));
        } finally {
            $this->sigmie->index($alias)?->delete();
        }
    }

    /** @dataProvider aliasesRequiringAPhysicalName */
    public function test_rebuild_resumes_aliases_that_need_a_different_physical_name(string $suffix): void
    {
        $alias = uniqid('.audit6-').$suffix;
        $physical = uniqid('.audit6-source-');
        $this->indexAPICall($physical, 'PUT', ['aliases' => [$alias => new stdClass]]);

        try {
            $pending = $this->sigmie->newIndex($alias)->replicas(0)->startRebuild();
            $resumed = $this->sigmie->rebuilding($alias);
            $this->assertSame($pending->name, $resumed?->name);
            $this->assertSame($pending->name, $resumed->finish(0)->name);
        } finally {
            $this->sigmie->index($alias)?->delete();
        }
    }

    public static function aliasesRequiringAPhysicalName(): array
    {
        return [['MixedCase'], [str_repeat('x', 230)]];
    }

    public function test_lifecycle_filtered_alias_keeps_an_unsigned_long_term(): void
    {
        $destination = 'source';
        $connection = $this->offlineConnection(function ($method, $path, array $body) use (&$destination): array|PsrResponse {
            if ($method === 'POST' && $path === '/_aliases') {
                $add = array_column($body['actions'], 'add')[0];
                $this->assertSame('18446744073709551615', $add['filter']['term']['id']);
                $destination = $add['index'];
            }

            return match (sprintf('%s %s', $method, $path)) {
                'GET /_alias/movies' => new PsrResponse(200, [], '{"source":{"aliases":{"movies":{"filter":{"term":{"id":18446744073709551615}}}}}}'),
                'PUT /source/_block/write' => ['shards_acknowledged' => true],
                'POST /_reindex' => ['failures' => [], 'timed_out' => false],
                'GET /movies' => [$destination => ['aliases' => ['movies' => []], 'mappings' => [], 'settings' => []]],
                default => ['acknowledged' => true],
            };
        });
        $index = new AliasedIndex('source', 'movies');
        $index->setElasticsearchConnection($connection);
        $index->update(fn (Update $update): Update => $update);
    }

    /** @dataProvider sharedSourceOperations */
    public function test_retiring_an_index_preserves_other_live_aliases(string $operation): void
    {
        $alias = uniqid('.audit6-shared-');
        $source = $this->sigmie->newIndex($alias)->replicas(0)->create();
        $pending = null;
        $other = $alias.'-secondary';
        $this->indexAPICall($source->name.'/_alias/'.$other, 'PUT');

        try {
            try {
                if ($operation === 'rebuild') {
                    $pending = $this->sigmie->newIndex($alias)->replicas(0)->startRebuild();
                    $pending->finish(0);
                } else {
                    $source->update(fn (Update $update): Update => $update->replicas(0));
                }
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString($other, $exception->getMessage());
            }

            $this->assertSame($source->name, $this->sigmie->index($other)?->name);
        } finally {
            $pending?->abort();
            $this->sigmie->index($alias)?->delete();
        }
    }

    public static function sharedSourceOperations(): array
    {
        return [['update'], ['rebuild']];
    }

    public function test_empty_search_synonyms_clear_the_rules_on_a_synonym_field(): void
    {
        $alias = uniqid('.audit6-synonyms-');
        $props = new NewProperties;
        $props->text('title')->searchSynonyms();
        $index = $this->sigmie->newIndex($alias)->properties($props)->replicas(0)->searchSynonyms(['car, auto'])->create();

        try {
            $updated = $index->update(fn (Update $update): NewIndex => $update->replicas(0)->properties($props)->searchSynonyms([]));
            $this->assertSame(['car'], $updated->analyze('car', 'default_with_synonyms'));
        } finally {
            $this->sigmie->index($alias)?->delete();
        }
    }

    public function test_update_refuses_to_lose_fields_excluded_from_source(): void
    {
        $alias = uniqid('.audit6-pruned-');
        $physical = $alias.'_old';
        $this->indexAPICall($physical, 'PUT', [
            'aliases' => [$alias => new stdClass],
            'mappings' => ['_source' => ['excludes' => ['tag']], 'properties' => ['tag' => ['type' => 'keyword']]],
        ]);

        try {
            $this->indexAPICall($alias.'/_doc/1?refresh=true', 'PUT', ['tag' => 'live']);
            try {
                $this->sigmie->index($alias)->update(fn (Update $update): Update => $update->replicas(0));
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('_source', $exception->getMessage());
            }

            $this->assertSame($physical, $this->sigmie->index($alias)->name);
            $this->assertSame(1, $this->indexAPICall($alias.'/_count', 'POST', [
                'query' => ['term' => ['tag' => 'live']],
            ])->json('count'));
        } finally {
            $this->sigmie->index($alias)?->delete();
        }
    }

    public function test_update_combines_raw_normalizers_with_the_requested_default_analyzer(): void
    {
        $alias = uniqid('audit6-analysis-');
        $source = $this->sigmie->newIndex($alias)->replicas(0)->create();

        try {
            $index = $source->update(fn (Update $update): Update => $update->replicas(0)
                ->uppercase('caps')
                ->config('analysis', ['normalizer' => ['fold' => ['type' => 'custom', 'filter' => ['lowercase']]]]));

            $this->assertSame(['MOVIES'], $index->analyze('Movies'));
            $this->assertSame(['lowercase'], $index->get('settings.index.analysis.normalizer.fold.filter'));
        } finally {
            $this->sigmie->index($alias)?->delete();
        }
    }

    public function test_last_setting_wins_across_prefixed_and_unprefixed_names(): void
    {
        $alias = uniqid('audit6-setting-');
        $source = $this->sigmie->newIndex($alias)->replicas(0)->create();

        try {
            $index = $source->update(fn (Update $update): Update => $update->replicas(0)
                ->config('max_result_window', 100)
                ->config('index.max_result_window', 200));

            $this->assertSame('200', $index->get('settings.index.max_result_window'));
        } finally {
            $this->sigmie->index($alias)?->delete();
        }
    }

    public function test_update_copies_documents_without_reapplying_the_default_pipeline(): void
    {
        $alias = uniqid('audit6-pipeline-');
        $this->indexAPICall('_ingest/pipeline/'.$alias, 'PUT', [
            'processors' => [['append' => ['field' => 'events', 'value' => 'ingested']]],
        ]);

        try {
            $index = $this->sigmie->newIndex($alias)->replicas(0)->config('default_pipeline', $alias)->create();
            $this->indexAPICall($alias.'/_doc/1', 'PUT', ['events' => []]);
            $index->update(fn (Update $update): Update => $update->replicas(0)->config('default_pipeline', $alias));

            $this->assertSame(['ingested'], $this->indexAPICall($alias.'/_doc/1', 'GET')->json('_source.events'));
            $this->indexAPICall($alias.'/_doc/2', 'PUT', ['events' => []]);
            $this->assertSame(['ingested'], $this->indexAPICall($alias.'/_doc/2', 'GET')->json('_source.events'));
        } finally {
            $this->sigmie->index($alias)?->delete();
            $this->indexAPICall('_ingest/pipeline/'.$alias, 'DELETE');
        }
    }

    public function test_hidden_rebuild_can_be_resumed(): void
    {
        $alias = uniqid('audit6-hidden-');
        $started = $this->sigmie->newIndex($alias)->config('index.hidden', true)->startRebuild();

        try {
            $this->assertSame($started->name, $this->sigmie->rebuilding($alias)?->name);
        } finally {
            $started->abort();
        }
    }

    public function test_audit_index_rebuild_returns_the_promoted_schema(): void
    {
        $alias = uniqid('audit-index-base-rebuild-schema-');
        $properties = new NewProperties;
        $properties->keyword('genre');

        $pending = $this->sigmie->newIndex($alias)->replicas(0)->shards(2)->properties($properties)->startRebuild();

        try {
            $index = $pending->finish(minDocuments: 0);

            $this->assertSame(2, $index->settings->primaryShards());
            $this->assertNotNull($index->mappings->properties()->get('genre'));
        } finally {
            $this->indexAPICall($pending->name, 'DELETE');
        }
    }

    public function test_audit_index_update_keeps_the_filter_on_the_alias(): void
    {
        $alias = uniqid('audit-index-base-cutover-');
        $physical = $alias.'_source';
        $destination = null;
        $this->indexAPICall($physical, 'PUT', [
            'settings' => ['number_of_replicas' => 0],
            'aliases' => [$alias => ['filter' => ['term' => ['visible' => true]]]],
        ]);

        try {
            $this->sigmie->collect($physical, true)->merge([
                new Document(['visible' => true], _id: 'public'),
                new Document(['visible' => false], _id: 'private'),
            ]);
            $this->assertSame(1, $this->sigmie->collect($alias)->count());

            $destination = $this->sigmie->index($alias)->update(fn (Update $builder): Update => $builder->replicas(0));
            $this->assertSame(2, $this->sigmie->collect($destination->name)->count());
            $this->assertSame(1, $this->sigmie->collect($alias)->count());
        } finally {
            if ($destination instanceof AliasedIndex) {
                $destination->delete();
            }

            if ($this->indexAPICall($physical, 'HEAD')->code() === 200) {
                $this->indexAPICall($physical, 'DELETE');
            }
        }
    }

    public function test_lifecycle_update_does_not_copy_after_a_failed_source_refresh(): void
    {
        $writesBlocked = false;
        $copied = false;
        $connection = $this->offlineConnection(function ($method, $path, array $body) use (&$writesBlocked, &$copied): array {
            return match (sprintf('%s %s', $method, $path)) {
                'PUT /source/_block/write' => ['shards_acknowledged' => $writesBlocked = true],
                'PUT /source/_settings' => ['acknowledged' => ! ($writesBlocked = $body['index']['blocks.write'])],
                'POST /source/_refresh' => ['_shards' => ['total' => 2, 'successful' => 1, 'failed' => 1]],
                'POST /_reindex' => ['copied' => $copied = true, 'failures' => [], 'timed_out' => false],
                'GET /movies' => ['destination' => [
                    'settings' => ['index' => []],
                    'mappings' => [],
                    'aliases' => ['movies' => []],
                ]],
                default => ['acknowledged' => true],
            };
        });
        $index = new AliasedIndex('source', 'movies');
        $index->setElasticsearchConnection($connection);

        try {
            $index->update(fn (Update $builder): Update => $builder);
            $this->fail('The source refresh failed.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(1, $elasticsearchException->json('_shards.failed'));
            $this->assertFalse($copied);
            $this->assertFalse($writesBlocked);
        }
    }

    public function test_update_keeps_external_document_versions(): void
    {
        $alias = uniqid('update-versions-');
        $index = $this->sigmie->newIndex($alias)->replicas(0)->create();
        $this->indexAPICall($alias.'/_doc/synced?version=50&version_type=external&refresh=true', 'PUT', ['title' => 'Synced']);

        $index = $index->update(fn (Update $update): Update => $update->replicas(0));
        $this->assertSame(50, $this->indexAPICall($alias.'/_doc/synced', 'GET')->json('_version'));

        $index->asyncUpdate(fn (Update $update): Update => $update->replicas(0))->waitAndFinish();
        $this->assertSame(50, $this->indexAPICall($alias.'/_doc/synced', 'GET')->json('_version'));
    }

    public function test_update_copies_an_index_with_a_small_result_window(): void
    {
        $alias = uniqid('update-window-');
        $configure = fn (NewIndex $builder): NewIndex => $builder->replicas(0)->config('max_result_window', 500);
        $index = $configure($this->sigmie->newIndex($alias))->create();
        $this->sigmie->collect($alias, refresh: true)->add(new Document(['title' => 'Kept'], 'kept'));

        $index = $index->update($configure);
        $this->assertSame(1, $this->sigmie->collect($alias)->count());

        $index->asyncUpdate($configure)->waitAndFinish();
        $this->assertSame(1, $this->sigmie->collect($alias)->count());
    }

    public function test_audit_index_update_does_not_promote_a_copy_with_routing_collisions(): void
    {
        $alias = uniqid('audit-index-base-routing-');
        $source = $this->sigmie->newIndex($alias)->replicas(0)->shards(2)->create();
        $destination = $alias.'_destination';

        try {
            $routes = [];
            for ($candidate = 1; count($routes) < 2 && $candidate <= 100; $candidate++) {
                $routing = 'route-'.$candidate;
                $shard = $this->indexAPICall($source->name.'/_search_shards?routing='.$routing, 'GET')->json('shards.0.0.shard');
                $routes[$shard] = $routing;
            }

            $this->assertCount(2, $routes);
            foreach ($routes as $routing) {
                $this->indexAPICall($source->name.'/_doc/shared?routing='.$routing.'&refresh=true', 'PUT', ['route' => $routing]);
            }

            $this->assertSame(2, $this->sigmie->collect($alias)->count());

            try {
                $source->update(fn (Update $builder): Update => new class($builder->getElasticsearchConnection(), $builder->getAlias(), $destination) extends Update
                {
                    public function __construct(ElasticsearchConnection $connection, string $alias, private string $destination)
                    {
                        parent::__construct($connection);
                        $this->alias($alias)->replicas(0)->shards(1);
                    }

                    protected function createIndexName(): string
                    {
                        return $this->destination;
                    }
                });
                $this->fail('Two routed documents with the same ID cannot share a destination shard.');
            } catch (ElasticsearchException $exception) {
                $this->assertSame('version_conflict_engine_exception', $exception->json('type'));
                $this->assertSame($source->name, $this->sigmie->index($alias)->name);
                $this->assertSame(2, $this->sigmie->collect($alias)->count());
            }
        } finally {
            foreach ([$source->name, $destination] as $name) {
                if ($this->indexAPICall($name, 'HEAD')->code() === 200) {
                    $this->indexAPICall($name, 'DELETE');
                }
            }
        }
    }

    protected function setUp(): void
    {
        if (! str_starts_with($this->getName(false), 'test_lifecycle_')
            && ! str_starts_with($this->getName(false), 'serverless_')) {
            parent::setUp();
        }
    }

    protected function tearDown(): void
    {
        if (! str_starts_with($this->getName(false), 'test_lifecycle_')
            && ! str_starts_with($this->getName(false), 'serverless_')) {
            parent::tearDown();
        }
    }

    private function offlineConnection(callable $respond): ElasticsearchConnection
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $owners = [];
        $connection->method('__invoke')->willReturnCallback(
            static function (ElasticsearchRequest $request) use ($respond, &$owners): ElasticsearchResponse {
                $path = rawurldecode($request->getUri()->getPath());
                $method = $request->getMethod();
                $body = json_decode((string) $request->getBody(), true) ?? [];
                $claim = $body['actions'][0]['add'] ?? [];
                if ($path === '/_aliases' && str_starts_with($claim['alias'] ?? '', '.sigmie-update-source-')) {
                    $owners['/'.$claim['alias'].'/_settings'] = '/'.$claim['index'].'/_settings';
                    $owners['/'.$claim['alias'].'/_block/write'] = '/'.$claim['index'].'/_block/write';

                    return $request->response(new PsrResponse(200, [], '{"acknowledged":true}'));
                }

                $path = $owners[$path] ?? $path;
                $data = $respond($method, $path, $body);

                return $request->response($data instanceof PsrResponse
                    ? $data
                    : new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode($data)));
            }
        );

        return $connection;
    }

    public function test_lifecycle_update_refreshes_after_blocking(): void
    {
        $this->checkUpdateSwap(false);
    }

    public function test_lifecycle_update_swaps_the_alias_atomically(): void
    {
        $this->checkUpdateSwap(true);
    }

    public function test_lifecycle_update_renames_the_alias_atomically(): void
    {
        $this->checkUpdateSwap(true, 'videos');
    }

    private function checkUpdateSwap(bool $assertSwap, string $newAlias = 'movies'): void
    {
        $writesBlocked = false;
        $sourceVisible = false;
        $copied = false;
        $actions = [];
        $dest = null;
        $connection = $this->offlineConnection(function ($method, $path, $body) use (&$writesBlocked, &$sourceVisible, &$copied, &$actions, &$dest, $newAlias): array {
            return match (sprintf('%s %s', $method, $path)) {
                'PUT /source/_settings' => ['blocked' => $writesBlocked = $body['index']['blocks.write']],
                'PUT /source/_block/write' => ['shards_acknowledged' => $writesBlocked = true],
                'POST /source/_refresh' => ['visible' => $sourceVisible = $writesBlocked],
                'POST /_reindex' => ['copied' => $copied = $sourceVisible, 'failures' => [], 'timed_out' => false],
                'POST /_aliases' => ['actions' => $actions = $body['actions'], 'acknowledged' => true],
                'GET /_alias/movies' => ['source' => []],
                'GET /_alias/videos' => [],
                'GET /'.$newAlias => [$dest => [
                    'aliases' => [$newAlias => []],
                    'mappings' => ['properties' => []],
                    'settings' => ['index' => ['number_of_shards' => 1, 'number_of_replicas' => 0]],
                ]],
                default => $method === 'PUT' && isset($body['mappings'])
                    ? ['acknowledged' => true, 'index' => $dest = ltrim($path, '/')]
                    : ['acknowledged' => true],
            };
        });

        $index = new AliasedIndex('source', 'movies');
        $index->setElasticsearchConnection($connection);
        $index->update(fn (Update $update): Update => $update->alias($newAlias));

        if (! $assertSwap) {
            $this->assertTrue($copied);

            return;
        }

        $this->assertSame('movies', $actions[0]['remove']['alias'] ?? null);
        $this->assertTrue($actions[0]['remove']['must_exist'] ?? false);
        $adds = array_column($actions, 'add');
        $this->assertSame($dest, $adds[0]['index']);
        $this->assertSame($newAlias, $adds[0]['alias']);
    }

    public function test_lifecycle_sync_reindex_failure_restores_source_writes(): void
    {
        $blocked = false;
        $connection = $this->offlineConnection(function ($method, $path, array $body) use (&$blocked): array {
            return match (sprintf('%s %s', $method, $path)) {
                'PUT /source/_settings' => ['blocked' => $blocked = $body['index']['blocks.write']],
                'PUT /source/_block/write' => ['shards_acknowledged' => $blocked = true],
                'POST /_reindex' => throw new RuntimeException('Reindex failed'),
                default => ['acknowledged' => true],
            };
        });

        $index = new AliasedIndex('source', 'movies');
        $index->setElasticsearchConnection($connection);

        try {
            $index->update(fn (Update $update): Update => $update);
            $this->fail('Expected reindex failure');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('Reindex failed', $runtimeException->getMessage());
        }

        $this->assertFalse($blocked);
    }

    public function test_lifecycle_async_start_failure_restores_writes_after_refreshing(): void
    {
        $blocked = false;
        $refreshed = false;
        $connection = $this->offlineConnection(function ($method, $path, array $body) use (&$blocked, &$refreshed): array {
            return match (sprintf('%s %s', $method, $path)) {
                'PUT /source/_settings' => ['blocked' => $blocked = $body['index']['blocks.write']],
                'PUT /source/_block/write' => ['shards_acknowledged' => $blocked = true],
                'POST /source/_refresh' => ['refreshed' => $refreshed = $blocked],
                'POST /_reindex' => throw new RuntimeException('Could not start reindex'),
                default => ['acknowledged' => true],
            };
        });

        $index = new AliasedIndex('source', 'movies');
        $index->setElasticsearchConnection($connection);

        try {
            $index->asyncUpdate(fn (Update $update): Update => $update);
            $this->fail('Expected reindex submission to fail');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('Could not start reindex', $runtimeException->getMessage());
        }

        $this->assertFalse($blocked);
        $this->assertTrue($refreshed);
    }

    public function test_lifecycle_unpack_resumes_the_same_task(): void
    {
        $starts = 0;
        $connection = $this->offlineConnection(function ($method, $path) use (&$starts): array {
            return match (sprintf('%s %s', $method, $path)) {
                'POST /_reindex' => ['task' => 'node:'.++$starts],
                'GET /_tasks/node:1' => ['completed' => false],
                default => throw new RuntimeException(sprintf('Unexpected request %s %s', $method, $path)),
            };
        });

        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 1);
        $resumed = IndexUpdateTask::unpack($connection, $task->pack());

        $this->assertSame(1, $starts);
        $this->assertFalse($resumed->isCompleted());
    }

    public function test_lifecycle_wait_promotes_on_the_last_poll_without_rereading_the_task(): void
    {
        $live = 'source';
        $polls = 0;
        $connection = $this->offlineConnection(function ($method, $path) use (&$live, &$polls): array {
            return match (sprintf('%s %s', $method, $path)) {
                'GET /_tasks/node:1' => ++$polls === 1
                    ? ['completed' => true, 'response' => ['failures' => [], 'timed_out' => false]]
                    : throw new ElasticsearchException(['type' => 'resource_not_found_exception'], 404),
                'HEAD /_alias/movies', 'PUT /dest/_settings', 'DELETE /source' => ['acknowledged' => true],
                'POST /_aliases' => ['acknowledged' => ($live = 'dest') === 'dest'],
                'GET /_alias/movies', 'GET /movies' => [$live => [
                    'aliases' => ['movies' => []], 'settings' => ['index' => []], 'mappings' => [],
                ]],
                default => throw new RuntimeException(sprintf('Unexpected request %s %s', $method, $path)),
            };
        });
        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 1, task: 'node:1');

        $task->waitAndFinish(maxTries: 0);

        $this->assertSame(1, $polls);
        $this->assertSame('dest', $live);
    }

    public function test_lifecycle_wait_can_be_retried_after_the_task_result_expires(): void
    {
        $checkedAlias = false;
        $connection = $this->offlineConnection(function ($method, $path) use (&$checkedAlias): array|PsrResponse {
            if ($method === 'HEAD' && str_starts_with($path, '/source/_alias/')) {
                return new PsrResponse(404);
            }

            return match (sprintf('%s %s', $method, $path)) {
                'GET /_tasks/node:1' => throw new ElasticsearchException(['type' => 'resource_not_found_exception'], 404),
                'HEAD /_alias/movies' => [],
                'GET /_alias/movies' => ['dest' => ['aliases' => ['movies' => []], 'checked' => $checkedAlias = true]],
                default => throw new RuntimeException(sprintf('Unexpected request %s %s', $method, $path)),
            };
        });
        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 1, task: 'node:1');

        $task->waitAndFinish(maxTries: 0);

        $this->assertTrue($checkedAlias);
    }

    /** @dataProvider retirementModes */
    public function test_lifecycle_finish_resumes_cleanup_after_promotion(bool $rebuild): void
    {
        $live = 'source';
        $retiredAlias = null;
        $sourceExists = true;
        $swaps = 0;
        $deletes = 0;
        $connection = $this->offlineConnection(function ($method, $path, array $body) use (&$live, &$sourceExists, &$retiredAlias, &$swaps, &$deletes): array|PsrResponse {
            if ($method === 'HEAD' && str_starts_with($path, '/source/_alias/')) {
                return new PsrResponse($sourceExists && $path === '/source/_alias/'.$retiredAlias ? 200 : 404);
            }

            if ($method === 'POST' && $path === '/_aliases') {
                $swaps++;
                foreach (array_column($body['actions'], 'add') as $action) {
                    if ($action['alias'] === 'movies') {
                        $live = $action['index'];
                    } elseif ($action['index'] === 'source' && ($action['is_hidden'] ?? false)) {
                        $retiredAlias = $action['alias'];
                    }
                }

                return ['acknowledged' => true];
            }

            if ($method === 'DELETE' && $path === '/source') {
                if (++$deletes === 1) {
                    throw new ElasticsearchException(['type' => 'process_cluster_event_timeout_exception'], 429);
                }

                $sourceExists = false;

                return ['acknowledged' => true];
            }

            return match (sprintf('%s %s', $method, $path)) {
                'HEAD /_alias/movies', 'POST /dest/_refresh' => [],
                'PUT /dest/_settings' => ['acknowledged' => true],
                'GET /dest/_count' => ['count' => 1],
                'GET /_tasks/node:1' => ['completed' => true, 'response' => ['failures' => [], 'timed_out' => false]],
                'GET /source', 'GET /_alias/movies', 'GET /movies' => [$live => [
                    'aliases' => ['movies' => []], 'settings' => ['index' => []], 'mappings' => [],
                ]],
                default => throw new RuntimeException(sprintf('Unexpected request %s %s', $method, $path)),
            };
        });
        $handle = $rebuild
            ? fn (): IndexRebuild => new IndexRebuild($connection, 'movies', 'dest', 1, startedOn: ['source'], refresh: '1s')
            : fn (): IndexUpdateTask => new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 1, task: 'node:1');

        try {
            $handle()->finish();
            $this->fail('Expected source deletion to fail.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(429, $elasticsearchException->getCode());
        }

        $this->assertSame('dest', $handle()->finish()->name);
        $this->assertFalse($sourceExists);
        $sourceExists = true;
        $retiredAlias = null; // A replacement index with the original name.
        $this->assertSame('dest', $handle()->finish()->name);
        $this->assertTrue($sourceExists);
        $this->assertSame(1, $swaps);
        $this->assertSame(2, $deletes);
    }

    public static function retirementModes(): array
    {
        return ['async update' => [false], 'rebuild' => [true]];
    }

    public function test_wait_and_finish_allows_time_between_pending_polls(): void
    {
        $readyAt = null;
        $finished = false;
        $connection = $this->offlineConnection(function ($method, $path) use (&$readyAt, &$finished): array|PsrResponse {
            if ($path === '/_tasks/node:1') {
                $readyAt ??= hrtime(true) + 500_000_000;

                return hrtime(true) < $readyAt
                    ? ['completed' => false]
                    : ['completed' => true, 'response' => ['timed_out' => false, 'failures' => []]];
            }

            if ($method === 'HEAD' && str_starts_with($path, '/source/_alias/')) {
                return new PsrResponse(404);
            }

            $finished = $path === '/movies';

            return match (sprintf('%s %s', $method, $path)) {
                'HEAD /_alias/movies' => [],
                'GET /_alias/movies', 'GET /movies' => [
                    'dest' => ['aliases' => ['movies' => []], 'mappings' => [], 'settings' => ['index' => []]],
                ],
                default => throw new RuntimeException(sprintf('Unexpected request %s %s', $method, $path)),
            };
        });

        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 1, task: 'node:1');
        $task->waitAndFinish(maxTries: 1);

        $this->assertTrue($finished);
    }

    public function test_task_polling_surfaces_async_reindex_failures_and_restores_writes(): void
    {
        $blocked = true;
        $connection = $this->offlineConnection(function ($method, $path, array $body) use (&$blocked): array {
            return match (sprintf('%s %s', $method, $path)) {
                'GET /_tasks/node:1' => ['completed' => true, 'response' => [
                    'timed_out' => false, 'version_conflicts' => 0,
                    'failures' => [['status' => 400, 'cause' => [
                        'type' => 'strict_dynamic_mapping_exception', 'reason' => 'unexpected field',
                    ]]],
                ]],
                'HEAD /source' => [],
                'PUT /source/_settings' => ['acknowledged' => ! ($blocked = $body['index']['blocks.write'])],
                default => throw new RuntimeException(sprintf('Unexpected request %s %s', $method, $path)),
            };
        });

        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 1, task: 'node:1');

        try {
            $task->isCompleted();
            $this->fail('Polling reported a failed copy as completed.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(400, $elasticsearchException->getCode());
            $this->assertStringContainsString('unexpected field', $elasticsearchException->getMessage());
            $this->assertFalse($blocked);
        }
    }

    /** @dataProvider failedReindexResults */
    public function test_lifecycle_failed_async_result_keeps_source_live_and_writable(array $result): void
    {
        $blocked = true;
        $deleted = [];
        $promoted = false;
        $connection = $this->offlineConnection(function ($method, $path, array $body) use ($result, &$blocked, &$deleted, &$promoted): array {
            return match (sprintf('%s %s', $method, $path)) {
                'POST /_reindex' => ['task' => 'node:1'],
                'GET /_tasks/node:1' => ['completed' => true, 'response' => $result],
                'PUT /source/_settings' => ['blocked' => $blocked = $body['index']['blocks.write']],
                'POST /_aliases' => ['acknowledged' => $promoted = true],
                'DELETE /source' => ['acknowledged' => (bool) ($deleted[] = $path)],
                'GET /movies' => throw new RuntimeException('Unexpected promotion'),
                default => ['acknowledged' => true],
            };
        });

        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 1);

        try {
            $task->finish();
            $this->fail('Expected failed reindex result to be rejected');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame($result, $elasticsearchException->json('response'));
            $this->assertFalse($promoted);
            $this->assertSame([], $deleted);
            $this->assertFalse($blocked);
        }
    }

    public static function failedReindexResults(): array
    {
        return [
            'partial failure' => [['failures' => [['cause' => ['type' => 'mapper_parsing_exception']]], 'timed_out' => false]],
            'canceled' => [['failures' => [], 'timed_out' => false, 'canceled' => 'by user request']],
        ];
    }

    public function test_lifecycle_missing_task_status_keeps_source_blocked_for_retry(): void
    {
        $blocked = true;
        $attempts = 0;
        $live = 'source';
        $sourceExists = true;
        $connection = $this->offlineConnection(function ($method, $path, array $body) use (&$blocked, &$attempts, &$live, &$sourceExists): array {
            return match (sprintf('%s %s', $method, $path)) {
                'HEAD /_alias/movies', 'HEAD /source' => [],
                'GET /_alias/movies' => [$live => ['aliases' => ['movies' => new stdClass]]],
                'GET /_tasks/node:1' => ++$attempts === 1
                    ? throw new ElasticsearchException(['type' => 'resource_not_found_exception'], 404)
                    : ['completed' => true, 'response' => ['total' => 1, 'created' => 1, 'failures' => [], 'timed_out' => false]],
                'PUT /source/_settings' => ['acknowledged' => ! ($blocked = $body['index']['blocks.write'])],
                'PUT /dest/_settings' => ['acknowledged' => true],
                'POST /_aliases' => ['acknowledged' => ($live = $body['actions'][1]['add']['index']) === 'dest'],
                'DELETE /source' => ['acknowledged' => ! ($sourceExists = false)],
                'GET /movies' => [$live => [
                    'aliases' => ['movies' => new stdClass],
                    'mappings' => ['properties' => new stdClass],
                    'settings' => ['index' => []],
                ]],
                default => throw new RuntimeException(sprintf('Unexpected request %s %s', $method, $path)),
            };
        });

        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 1, task: 'node:1');

        try {
            $task->finish();
            $this->fail('Expected the first task lookup to fail');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(404, $elasticsearchException->getCode());
        }

        $this->assertTrue($blocked);
        $this->assertTrue($sourceExists);
        $this->assertSame('source', $live);

        $resumed = IndexUpdateTask::unpack($connection, $task->pack());
        $this->assertSame('dest', $resumed->finish()->name);
        $this->assertFalse($sourceExists);
    }

    public function test_lifecycle_abort_cannot_delete_a_concurrently_promoted_rebuild(): void
    {
        $this->checkConcurrentRebuildDecision(finishFirst: true);
    }

    public function test_lifecycle_finish_cannot_promote_a_concurrently_aborted_rebuild(): void
    {
        $this->checkConcurrentRebuildDecision(finishFirst: false);
    }

    public function test_lifecycle_abort_can_retry_a_failed_delete(): void
    {
        $this->checkConcurrentRebuildDecision(finishFirst: false, failDelete: true);
    }

    private function checkConcurrentRebuildDecision(bool $finishFirst, bool $failDelete = false): void
    {
        $source = 'source';
        $indices = [$source => ['aliases' => ['movies' => []]]];
        $rebuild = null;
        $abortStarted = false;
        $finishAttempted = false;
        $finish = function () use (&$finishAttempted, &$rebuild, $finishFirst): void {
            $finishAttempted = true;
            try {
                $rebuild->finish();
                $this->assertTrue($finishFirst, 'An aborted rebuild was promoted.');
            } catch (ElasticsearchException $elasticsearchException) {
                $this->assertFalse($finishFirst, $elasticsearchException->getMessage());
                $this->assertSame(404, $elasticsearchException->getCode());
            }
        };

        $deleteFailed = false;
        $connection = $this->offlineConnection(function ($method, $path, $body) use (&$indices, &$rebuild, &$abortStarted, &$finishAttempted, &$deleteFailed, $finish, $finishFirst, $failDelete): array|PsrResponse {
            $name = ltrim($path, '/');
            if ($method === 'PUT' && isset($body['mappings'])) {
                $indices[$name] = ['aliases' => [], ...$body];

                return ['acknowledged' => true];
            }

            if ($abortStarted && ! $finishAttempted && $finishFirst
                && (($method === 'HEAD' && $name === $rebuild->name) || ($method === 'POST' && $name === '_aliases'))) {
                $finish();
            }

            if ($method === 'POST' && $name === '_aliases') {
                $updated = $indices;
                foreach ($body['actions'] as $action) {
                    $change = reset($action);
                    $index = $change['index'];
                    $alias = $change['alias'];
                    if (isset($action['add'])) {
                        $updated[$index]['aliases'][$alias] = [];
                    }

                    if (isset($action['remove'])) {
                        if (! isset($updated[$index]['aliases'][$alias]) && ($change['must_exist'] ?? false)) {
                            throw new ElasticsearchException(['type' => 'aliases_not_found_exception'], 404);
                        }

                        unset($updated[$index]['aliases'][$alias]);
                    }
                }

                $indices = $updated;

                return ['acknowledged' => true];
            }

            if ($method === 'DELETE') {
                if (! $finishAttempted && ! $finishFirst) {
                    $finish();
                }

                if ($failDelete && ! $deleteFailed) {
                    $deleteFailed = true;

                    return ['acknowledged' => false];
                }

                unset($indices[$name]);

                return ['acknowledged' => true];
            }

            if ($method === 'HEAD' && str_contains($path, '/_alias/') && $path !== '/_alias/movies') {
                [$index, $alias] = explode('/_alias/', $name, 2);

                return new PsrResponse(isset($indices[$index]['aliases'][$alias]) ? 200 : 404);
            }

            return match (true) {
                in_array($path, ['/_alias/movies', '/movies'], true) => array_filter($indices, fn (array $index): bool => isset($index['aliases']['movies'])),
                $path === '/movies_*' => $indices,
                str_ends_with($path, '/_count') => ['count' => 1],
                default => ['acknowledged' => true],
            };
        });

        $started = (new NewIndex($connection))->alias('movies')->startRebuild();
        $sigmie = new Sigmie($connection);
        $rebuild = $sigmie->rebuilding('movies');
        $this->assertSame($started->name, $rebuild?->name);

        $abortStarted = true;
        if ($failDelete) {
            try {
                $started->abort();
                $this->fail('Expected delete failure.');
            } catch (RuntimeException $exception) {
                $this->assertSame(sprintf("Deletion of '%s' was not acknowledged.", $started->name), $exception->getMessage());
            }

            $this->assertArrayHasKey($started->name, $indices);
            $this->assertNull($sigmie->rebuilding('movies'));
        }

        $started->abort();

        $this->assertTrue($finishAttempted);
        $expectedLive = $finishFirst ? $started->name : $source;
        $this->assertSame([$expectedLive], array_keys($indices), 'The live index was deleted.');
        $this->assertArrayHasKey('movies', $indices[$expectedLive]['aliases']);
    }

    public function test_lifecycle_wait_does_not_spend_all_attempts_before_the_task_can_finish(): void
    {
        $started = null;
        $live = 'source';
        $connection = $this->offlineConnection(function ($method, $path, array $body) use (&$started, &$live): array {
            return match (sprintf('%s %s', $method, $path)) {
                'HEAD /_alias/movies' => [],
                'GET /_alias/movies' => [$live => ['aliases' => ['movies' => new stdClass]]],
                'GET /movies' => [$live => ['aliases' => ['movies' => new stdClass], 'settings' => ['index' => []], 'mappings' => []]],
                'GET /_tasks/node:1' => hrtime(true) - ($started ??= hrtime(true)) < 500_000_000
                    ? ['completed' => false]
                    : ['completed' => true, 'response' => ['total' => 1, 'created' => 1, 'failures' => [], 'timed_out' => false]],
                'POST /_aliases' => ['acknowledged' => ($live = $body['actions'][1]['add']['index']) === 'dest'],
                default => ['acknowledged' => true],
            };
        });

        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 1, task: 'node:1');
        $task->waitAndFinish(maxTries: 1);

        $this->assertSame('dest', $live);
    }

    public function test_lifecycle_does_not_promote_before_destination_settings_are_acknowledged(): void
    {
        $live = 'source';
        $attempts = 0;
        $connection = $this->offlineConnection(function ($method, $path, array $body) use (&$live, &$attempts): array {
            return match (sprintf('%s %s', $method, $path)) {
                'HEAD /_alias/movies' => [],
                'GET /_alias/movies' => [$live => ['aliases' => ['movies' => new stdClass]]],
                'GET /movies' => [$live => ['aliases' => ['movies' => new stdClass], 'settings' => ['index' => []], 'mappings' => []]],
                'GET /_tasks/node:1' => ['completed' => true, 'response' => ['total' => 1, 'created' => 1, 'failures' => [], 'timed_out' => false]],
                'PUT /dest/_settings' => ['acknowledged' => ++$attempts > 1],
                'POST /_aliases' => ['acknowledged' => ($live = $body['actions'][1]['add']['index']) === 'dest'],
                'DELETE /source' => ['acknowledged' => true],
                default => throw new RuntimeException(sprintf('Unexpected request %s %s', $method, $path)),
            };
        });

        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 1, task: 'node:1');
        try {
            $task->finish();
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertFalse($elasticsearchException->json('acknowledged'));
        }

        $this->assertSame('source', $live);
        $this->assertSame('dest', $task->finish()->name);
    }

    public static function cleanupModes(): array
    {
        return [...self::updateModes(), 'source replaced before retry' => ['sync', true]];
    }

    /** @dataProvider cleanupModes */
    public function test_lifecycle_retries_retired_index_cleanup_after_cutover(string $mode, bool $recreated = false): void
    {
        $live = 'source';
        $dest = 'dest';
        $deletions = 0;
        $promotions = 0;
        $cleanupAcknowledged = false;
        $retiredAlias = null;

        $connection = $this->offlineConnection(function ($method, $path, $body) use (&$live, &$dest, &$deletions, &$promotions, &$cleanupAcknowledged, &$retiredAlias, $recreated): array|PsrResponse {
            if ($method === 'POST' && $path === '/_aliases') {
                foreach (array_column($body['actions'], 'add') as $action) {
                    if ($action['index'] === 'source') {
                        $retiredAlias = $action['alias'];
                    }
                }
            }

            if ($method === 'HEAD' && str_starts_with($path, '/source/_alias/')) {
                $marked = $path === '/source/_alias/'.$retiredAlias && ! ($recreated && $deletions > 0);

                return new PsrResponse($marked ? 200 : 404);
            }

            if ($method === 'PUT' && isset($body['mappings'])) {
                $dest = ltrim($path, '/');

                return ['acknowledged' => true];
            }

            return match (sprintf('%s %s', $method, $path)) {
                'HEAD /_alias/movies' => [],
                'GET /_alias/movies' => [$live => ['aliases' => ['movies' => new stdClass]]],
                'GET /movies' => [$live => ['aliases' => ['movies' => new stdClass], 'settings' => ['index' => []], 'mappings' => []]],
                'PUT /source/_block/write' => ['shards_acknowledged' => true],
                'POST /_reindex' => ['total' => 1, 'created' => 1, 'failures' => [], 'timed_out' => false],
                'GET /_tasks/node:1' => ['completed' => true, 'response' => ['total' => 1, 'created' => 1, 'failures' => [], 'timed_out' => false]],
                'POST /source/_refresh' => ['_shards' => ['failed' => 0]],
                'POST /_aliases' => ['acknowledged' => ++$promotions === 1 && ($live = $dest) !== 'source'],
                'GET /dest/_count' => ['count' => 1],
                'DELETE /source' => ['acknowledged' => $cleanupAcknowledged = ++$deletions >= 2],
                default => ['acknowledged' => true],
            };
        });

        $old = new AliasedIndex('source', 'movies');
        $old->setElasticsearchConnection($connection);

        $task = new IndexUpdateTask($connection, 'source', $dest, 'movies', 'movies', 1, task: 'node:1');
        $finish = match ($mode) {
            'sync' => fn (): AliasedIndex => $old->update(fn (Update $update): Update => $update),
            'async' => fn (): AliasedIndex => IndexUpdateTask::unpack($connection, $task->pack())->finish(),
            'rebuild' => fn (): AliasedIndex => (new IndexRebuild($connection, 'movies', $dest, 1, startedOn: ['source']))->finish(),
        };

        $error = null;
        try {
            $finish();
        } catch (RuntimeException $runtimeException) {
            $error = $runtimeException;
        }

        $this->assertInstanceOf(RuntimeException::class, $error);
        $this->assertSame($dest, $live);
        $this->assertSame($dest, $finish()->name);
        $this->assertSame(! $recreated, $cleanupAcknowledged);
        $this->assertSame(1, $promotions);
        $this->assertSame($recreated ? 1 : 2, $deletions);
    }

    public static function updateModes(): array
    {
        return [['sync'], ['async'], ['rebuild']];
    }

    public function test_lifecycle_repeated_finish_preserves_the_live_index(): void
    {
        $this->checkLiveRebuild(false);
    }

    public function test_lifecycle_abort_preserves_the_live_index(): void
    {
        $this->checkLiveRebuild(true);
    }

    private function checkLiveRebuild(bool $abort): void
    {
        $deleted = [];
        $connection = $this->offlineConnection(function ($method, $path) use (&$deleted): array|PsrResponse {
            return match (true) {
                $method === 'POST' && $path === '/_aliases' => throw new ElasticsearchException(['type' => 'aliases_not_found_exception'], 404),
                $method === 'HEAD' && str_starts_with($path, '/dest/_alias/') => new PsrResponse(404),
                $method === 'GET' && $path === '/_alias/movies' => ['dest' => []],
                $method === 'GET' && $path === '/movies' => ['dest' => [
                    'aliases' => ['movies' => []],
                    'settings' => ['index' => []],
                    'mappings' => [],
                ]],
                $method === 'GET' && $path === '/dest/_count' => ['count' => 1],
                $method === 'DELETE' && $path === '/dest' => ['acknowledged' => (bool) ($deleted[] = $path)],
                default => ['acknowledged' => true],
            };
        });

        $rebuild = new IndexRebuild($connection, 'movies', 'dest', 1, startedOn: ['source']);
        if ($abort) {
            $rebuild->abort();
        } else {
            $this->assertSame('dest', $rebuild->finish(minDocuments: 2)->name);
        }

        $this->assertSame([], $deleted);
    }

    public function test_lifecycle_stale_rebuild_is_rejected_before_promotion(): void
    {
        $connection = $this->offlineConnection(static fn ($method, $path): array => match (sprintf('%s %s', $method, $path)) {
            'GET /_alias/movies' => ['winner' => []],
            'GET /loser/_count' => ['count' => 1],
            'POST /_aliases' => throw new RuntimeException('Stale promotion attempted'),
            default => ['acknowledged' => true],
        });

        $rebuild = new IndexRebuild($connection, 'movies', 'loser', 1, startedOn: ['source']);
        $this->expectException(RebuildLost::class);
        $rebuild->finish(minDocuments: 0);
    }

    public function test_lifecycle_rebuild_rejects_a_swap_that_lost_its_source(): void
    {
        $targets = ['source'];
        $deleted = [];
        $connection = $this->offlineConnection(function ($method, $path, array $body) use (&$targets, &$deleted): array {
            if ($method === 'POST' && $path === '/_aliases') {
                $targets = ['winner'];
                if ($body['actions'][0]['remove']['must_exist'] ?? false) {
                    throw new RuntimeException('Source no longer owns the alias');
                }

                $targets[] = 'loser';
            }

            if ($method === 'DELETE') {
                $deleted[] = $path;
            }

            return match (sprintf('%s %s', $method, $path)) {
                'GET /_alias/movies' => array_fill_keys($targets, []),
                'GET /loser/_count' => ['count' => 1],
                default => ['acknowledged' => true],
            };
        });

        $rebuild = new IndexRebuild($connection, 'movies', 'loser', 1, startedOn: ['source']);
        try {
            $rebuild->finish();
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('Source no longer owns the alias', $runtimeException->getMessage());
        }

        $this->assertSame(['winner'], $targets);
        $this->assertSame([], $deleted);
    }

    public function test_lifecycle_rebuild_preserves_builder_after_failed_creation(): void
    {
        $creates = 0;
        $connection = $this->offlineConnection(function ($method, $path, $body) use (&$creates): array {
            if ($method === 'PUT' && isset($body['mappings']) && ++$creates === 1) {
                throw new RuntimeException('Create failed');
            }

            return ['acknowledged' => true];
        });

        $builder = (new NewIndex($connection))->alias('movies')->replicas(2)->config('refresh_interval', '13s');

        try {
            $builder->startRebuild();
            $this->fail('Expected index creation failure');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('Create failed', $runtimeException->getMessage());
        }

        $index = $builder->make();
        $this->assertSame('13s', $index->settings->toRaw()['refresh_interval']);
        $this->assertSame(2, $index->settings->replicaShards());
        $this->assertArrayNotHasKey(IndexRebuild::META_KEY, (array) ($index->mappings->toRaw(new Elasticsearch)['_meta'] ?? []));
    }

    public function test_lifecycle_rebuild_restores_configured_refresh_interval(): void
    {
        $this->checkRebuildRefresh(false);
    }

    public function test_lifecycle_resumed_rebuild_restores_configured_refresh_interval(): void
    {
        $this->checkRebuildRefresh(true);
    }

    private function checkRebuildRefresh(bool $resume): void
    {
        $refresh = null;
        $name = null;
        $created = [];
        $connection = $this->offlineConnection(function ($method, $path, $body) use (&$refresh, &$name, &$created): array|PsrResponse {
            if ($method === 'PUT' && isset($body['mappings'])) {
                $name = ltrim($path, '/');
                $created = $body;
            }

            if ($method === 'PUT' && str_ends_with($path, '/_settings')) {
                $refresh = $body['refresh_interval'] ?? null;
            }

            return match (true) {
                $path === '/_alias/movies' => [],
                $method === 'HEAD' && $path === '/movies' => new PsrResponse(404),
                $path === '/movies_*', str_ends_with($path, '/_mapping') => [$name => $created],
                $path === '/movies' => [$name => [
                    'aliases' => ['movies' => []],
                    'settings' => ['index' => $created['settings']],
                    'mappings' => $created['mappings'],
                ]],
                str_ends_with($path, '/_count') => ['count' => 1],
                default => ['acknowledged' => true],
            };
        });

        $rebuild = (new NewIndex($connection))->alias('movies')->config('index.refresh_interval', '30s')->startRebuild();

        $this->assertSame('-1', $created['settings']['refresh_interval']);
        $this->assertArrayNotHasKey('index.refresh_interval', $created['settings']);
        $this->assertSame('30s', $created['mappings']['_meta'][IndexRebuild::META_REFRESH]);

        if ($resume) {
            $rebuild = (new Sigmie($connection))->rebuilding('movies');
        }

        $rebuild->finish(0);

        $this->assertSame('30s', $refresh);
    }

    /**
     * @test
     */
    public function async_update(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->stopwords(['foo', 'bar'], 'demo')
            ->mapChars(['foo' => 'bar'], 'some_char_filter_name')
            ->stripHTML()
            ->create();

        $docs = [];
        for ($i = 0; $i < 10; $i++) {
            $docs[] = new Document(['foo' => 'bar']);
        }

        $collection = $this->sigmie->collect($alias, true);
        $collection->merge($docs);

        $indexUpdateTask = $index->asyncUpdate(function (Update $update): Update {
            $update->stopwords(['foo', 'bar'], 'demo');
            $update->config('index.refresh_interval', '30s');

            return $update;
        });
        $indexUpdateTask = IndexUpdateTask::unpack($this->elasticsearchConnection, $indexUpdateTask->pack());

        // wait until isComplete method returns true
        while (true) {
            if ($indexUpdateTask->isCompleted()) {
                $indexUpdateTask->finish();
                break;
            }

            // Sleep for a short interval before checking the condition again
            usleep(100000); // 100 milliseconds
        }

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasNotCharFilter('default', 'html_strip');
            $index->assertAnalyzerHasNotCharFilter('default', 'some_char_filter_name');
        });
        $this->assertSame('30s', $this->sigmie->index($alias)->settings->toRaw()['refresh_interval']);
    }

    /**
     * @test
     */
    public function analyzer_remove_html_char_filters(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->stopwords(['foo', 'bar'], 'demo')
            ->mapChars(['foo' => 'bar'], 'some_char_filter_name')
            ->stripHTML('html_strip')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasFilter('default', 'demo');
            $index->assertFilterHasStopwords('demo', ['foo', 'bar']);
            $index->assertAnalyzerHasCharFilter('default', 'html_strip');
            $index->assertAnalyzerHasCharFilter('default', 'some_char_filter_name');
        });

        $index->update(function (Update $update): Update {
            $update->stopwords(['foo', 'bar'], 'demo');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasNotCharFilter('default', 'html_strip');
            $index->assertAnalyzerHasNotCharFilter('default', 'some_char_filter_name');
        });
    }

    /**
     * @test
     */
    public function update_char_filter(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->mapChars(['bar' => 'baz'], 'map_chars_char_filter')
            ->patternReplace('/bar/', 'foo', 'pattern_replace_char_filter')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasCharFilter('default', 'map_chars_char_filter');
            $index->assertCharFilterEquals('map_chars_char_filter', [
                'type' => 'mapping',
                'mappings' => ['bar => baz'],
            ]);

            $index->assertAnalyzerHasCharFilter('default', 'pattern_replace_char_filter');
            $index->assertCharFilterEquals('pattern_replace_char_filter', [
                'type' => 'pattern_replace',
                'pattern' => '/bar/',
                'replacement' => 'foo',
            ]);
        });

        $index->update(function (Update $update): Update {
            $update->mapChars(['baz' => 'foo'], 'map_chars_char_filter');
            $update->patternReplace('/doe/', 'john', 'pattern_replace_char_filter');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasCharFilter('default', 'map_chars_char_filter');
            $index->assertCharFilterEquals('map_chars_char_filter', [
                'type' => 'mapping',
                'mappings' => ['baz => foo'],
            ]);

            $index->assertAnalyzerHasCharFilter('default', 'pattern_replace_char_filter');
            $index->assertCharFilterEquals('pattern_replace_char_filter', [
                'type' => 'pattern_replace',
                'pattern' => '/doe/',
                'replacement' => 'john',
            ]);
        });
    }

    /**
     * @test
     */
    public function default_char_filter(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerCharFilterIsEmpty('default');
        });

        $index->update(function (Update $update): Update {
            $update->patternReplace('/foo/', 'bar', 'default_pattern_replace_filter');
            $update->mapChars(['foo' => 'bar'], 'default_mappings_filter');
            $update->stripHTML('html_strip');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasCharFilter('default', 'default_pattern_replace_filter');
            $index->assertAnalyzerHasCharFilter('default', 'default_mappings_filter');
            $index->assertAnalyzerHasCharFilter('default', 'html_strip');
        });
    }

    /**
     * @test
     */
    public function default_tokenizer_configurable(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerCharFilterIsEmpty('default');
            $index->assertAnalyzerHasTokenizer('default', 'standard');
        });

        $index->update(function (Update $update): Update {
            $update->tokenizeOnPattern('/foo/', name: 'default_analyzer_pattern_tokenizer');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasTokenizer('default', 'default_analyzer_pattern_tokenizer');
            $index->assertTokenizerEquals('default_analyzer_pattern_tokenizer', [
                'pattern' => '/foo/',
                'type' => 'pattern',
            ]);
        });
    }

    /**
     * @test
     */
    public function default_tokenizer(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->tokenizer(new Whitespace)
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerTokenizerIsWhitespaces('default');
        });

        $index->update(function (Update $update): Update {
            $update->tokenizeOnWordBoundaries('foo_tokenizer');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertAnalyzerHasTokenizer('default', 'foo_tokenizer');
        });
    }

    /**
     * @test
     */
    public function update_index_one_way_synonyms(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->oneWaySynonyms([
                ['ipod', ['i-pod', 'i pod']],
            ], 'bar_name')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterExists('bar_name');
            $index->assertFilterHasSynonyms('bar_name', [
                'i-pod, i pod => ipod',
            ]);
        });

        $index->update(function (Update $update): Update {
            $update->oneWaySynonyms([
                ['mickey', ['mouse', 'goofy']],
            ], 'bar_name');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterExists('bar_name');
            $index->assertFilterHasSynonyms('bar_name', [
                'mouse, goofy => mickey',
            ]);
        });
    }

    /**
     * @test
     */
    public function update_index_stemming(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->stemming([
                ['am', ['be', 'are']],
                ['mouse', ['mice']],
                ['feet', ['foot']],
            ], 'bar_name')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterExists('bar_name');
            $index->assertFilterHasStemming('bar_name', [
                'be, are => am',
                'mice => mouse',
                'foot => feet',
            ]);
        });

        $index->update(function (Update $update): Update {
            $update->stemming([[
                'mickey', ['mouse', 'goofy'],
            ]], 'bar_name');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterExists('bar_name');
            $index->assertFilterHasStemming('bar_name', [
                'mouse, goofy => mickey',
            ]);
        });
    }

    /**
     * @test
     */
    public function update_index_synonyms(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->twoWaySynonyms([
                ['treasure', 'gem', 'gold', 'price'],
                ['friend', 'buddy', 'partner'],
            ], 'foo_two_way_synonyms', )
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterExists('foo_two_way_synonyms');
            $index->assertFilterHasSynonyms('foo_two_way_synonyms', [
                'treasure, gem, gold, price',
                'friend, buddy, partner',
            ]);
        });

        $index->update(function (Update $update): Update {
            $update->twoWaySynonyms([['john', 'doe']], 'foo_two_way_synonyms');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterHasSynonyms('foo_two_way_synonyms', [
                'john, doe',
            ]);
        });
    }

    /**
     * @test
     */
    public function update_index_stopwords(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->stopwords(['foo', 'bar', 'baz'], 'foo_stopwords')
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterExists('foo_stopwords');
            $index->assertFilterHasStopwords('foo_stopwords', ['foo', 'bar', 'baz']);
        });

        $index->update(function (Update $update): Update {
            $update->stopwords(['john', 'doe'], 'foo_stopwords');

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertFilterExists('foo_stopwords');
            $index->assertFilterHasStopwords('foo_stopwords', ['john', 'doe']);
        });
    }

    /**
     * @test
     */
    public function mappings(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->mapping(function (NewProperties $blueprint): NewProperties {
                $blueprint->text('bar')->searchAsYouType();
                $blueprint->text('created_at')->unstructuredText();

                return $blueprint;
            })
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertPropertyIsUnstructuredText('created_at');
        });

        $index->update(function (Update $update): Update {
            $update->mapping(function (NewProperties $blueprint): NewProperties {
                $blueprint->date('created_at');
                $blueprint->number('count')->float();

                return $blueprint;
            });

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertPropertyExists('count');
            $index->assertPropertyExists('created_at');
            $index->assertPropertyIsDate('created_at');
        });
    }

    /**
     * @test
     */
    public function reindex_docs(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->create();

        $oldIndexName = $index->name;

        $docs = [];
        for ($i = 0; $i < 10; $i++) {
            $docs[] = new Document(['foo' => 'bar']);
        }

        $collection = $this->sigmie->collect($alias, true);
        $collection->merge($docs);

        $this->assertCount(10, $collection);

        $updatedIndex = $index->update(function (Update $update): Update {
            $update->replicas(3);
            $update->config('index.refresh_interval', '30s');

            return $update;
        });

        $collection = $this->sigmie->collect($alias, true);

        $this->assertNotEquals($oldIndexName, $updatedIndex->name);
        $this->assertCount(10, $collection);
        $this->assertSame('30s', $updatedIndex->settings->toRaw()['refresh_interval']);
    }

    /**
     * @test
     */
    public function delete_old_index(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->create();

        $oldIndexName = $index->name;

        $index = $index->update(fn (Update $update): Update => $update);

        $this->assertIndexNotExists($oldIndexName);
        $this->assertNotEquals($oldIndexName, $index->name);
    }

    /**
     * @test
     */
    public function index_name(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->create();

        $oldIndexName = $index->name;

        $index = $index->update(fn (Update $update): Update => $update);

        $this->assertIndexExists($index->name);
        $this->assertIndexNotExists($oldIndexName);
        $this->assertNotEquals($oldIndexName, $index->name);
    }

    /**
     * @test
     */
    public function change_index_alias(): void
    {
        $oldAlias = uniqid();
        $newAlias = uniqid();

        $index = $this->sigmie->newIndex($oldAlias)
            ->create();

        $this->assertInstanceOf(AliasedIndex::class, $index);

        $index->update(function (Update $update) use ($newAlias): Update {
            $update->alias($newAlias);

            return $update;
        });

        $this->sigmie->index($newAlias);

        $oldIndex = $this->sigmie->index($oldAlias);

        $this->assertNull($oldIndex);
    }

    /**
     * @test
     */
    public function index_shards_and_replicas(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->shards(1)
            ->replicas(1)
            ->create();

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertShards(1);
            $index->assertReplicas(1);
        });

        $index->update(function (Update $update): Update {
            $update->replicas(2)->shards(2);

            return $update;
        });

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertShards(2);
            $index->assertReplicas(2);
        });
    }

    /**
     * @test
     */
    public function update_index_wait_and_finish(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->shards(1)
            ->replicas(1)
            ->create();

        $oldName = $this->sigmie->index($alias)->raw['settings']['index']['provided_name'];

        $task = $index->asyncUpdate(function (Update $update): Update {
            $update->replicas(2)->shards(2);

            return $update;
        });

        $task->waitAndFinish();

        $newName = $this->sigmie->index($alias)->raw['settings']['index']['provided_name'];

        $this->assertNotEquals($oldName, $newName);
    }

    /**
     * @test
     */
    public function serverless_update_omits_replicas_and_uses_5s_refresh_interval(): void
    {
        $alias = 'alias_'.uniqid();
        $sourceName = 'src_'.uniqid();

        $connection = new class($sourceName) implements ElasticsearchConnection
        {
            public array $settingsCalls = [];

            private ?string $destination = null;

            public function __construct(private string $source) {}

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $path = rawurldecode($request->getUri()->getPath());
                $method = $request->getMethod();
                $body = json_decode((string) $request->getBody(), true) ?? [];

                if (str_ends_with($path, '/_block/write')) {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"shards_acknowledged":true}'));
                }

                if ($method === 'PUT' && str_ends_with($path, '/_settings')) {
                    $this->settingsCalls[] = $body;

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($method === 'PUT') {
                    $this->destination = ltrim($path, '/');

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($path === '/_reindex') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"failures":[],"timed_out":false}'));
                }

                if ($method === 'POST') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($method === 'DELETE') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if (str_starts_with($path, '/_alias/')) {
                    return $request->response(new PsrResponse(200, [], json_encode([$this->source => new stdClass])));
                }

                if ($method === 'GET' && ! str_starts_with($path, '/_resolve')) {
                    $aliasName = ltrim($path, '/');
                    $data = [
                        ($this->destination ?? $aliasName.'_concrete') => [
                            'aliases' => [$aliasName => new stdClass],
                            'mappings' => ['properties' => new stdClass],
                            'settings' => ['index' => ['number_of_shards' => '1', 'number_of_replicas' => '0']],
                        ],
                    ];

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) json_encode($data)));
                }

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"indices":[]}'));
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                return new FulfilledPromise($this($request));
            }

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return true;
            }
        };

        $index = new AliasedIndex($sourceName, $alias);
        $index->setElasticsearchConnection($connection);

        $index->update(fn (Update $update): Update => $update);

        $afterReindex = array_values(array_filter(
            $connection->settingsCalls,
            fn ($s): bool => isset($s['refresh_interval'])
        ))[0] ?? [];

        $this->assertSame('5s', $afterReindex['refresh_interval']);
        $this->assertArrayNotHasKey('number_of_replicas', $afterReindex);
    }

    /**
     * @test
     */
    public function serverless_finish_omits_replicas_and_uses_5s_refresh_interval(): void
    {
        $source = 'src_'.uniqid();
        $dest = 'dst_'.uniqid();
        $alias = 'alias_'.uniqid();

        $connection = new class($dest, $source) implements ElasticsearchConnection
        {
            public array $settingsCalls = [];

            public function __construct(private string $dest, private string $source) {}

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $path = rawurldecode($request->getUri()->getPath());
                $method = $request->getMethod();

                if ($method === 'PUT' && str_ends_with($path, '/_settings')) {
                    $this->settingsCalls[] = json_decode((string) $request->getBody(), true) ?? [];

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if (str_starts_with($path, '/_alias/')) {
                    return $request->response(new PsrResponse(200, [], json_encode([$this->source => new stdClass])));
                }

                if ($path === '/_tasks/node:1') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"completed":true,"response":{"failures":[],"timed_out":false}}'));
                }

                if ($path === '/_reindex') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"task":"node:1"}'));
                }

                if ($path === '/_aliases') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($method === 'DELETE') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($method === 'GET' && ! str_starts_with($path, '/_resolve')) {
                    $aliasName = ltrim($path, '/');
                    $data = [
                        $this->dest => [
                            'aliases' => [$aliasName => new stdClass],
                            'mappings' => ['properties' => new stdClass],
                            'settings' => ['index' => ['number_of_shards' => '1', 'number_of_replicas' => '0']],
                        ],
                    ];

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) json_encode($data)));
                }

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"indices":[]}'));
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                return new FulfilledPromise($this($request));
            }

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return true;
            }
        };

        $task = new IndexUpdateTask($connection, $source, $dest, $alias, $alias, 2);
        $task->finish();

        $settings = $connection->settingsCalls[0] ?? [];
        $this->assertSame('5s', $settings['refresh_interval']);
        $this->assertArrayNotHasKey('number_of_replicas', $settings);
    }

    /**
     * @test
     */
    public function index_update_task_pack_unpack_and_timeout_paths_after_elasticsearch_hit(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Update task coverage'], _id: 'matching'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Update')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $connection = new class implements ElasticsearchConnection
        {
            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $json = match (rawurldecode($request->getUri()->getPath())) {
                    '/_reindex' => '{"task":"node:1"}',
                    '/_tasks/node:1' => '{"completed":false}',
                    default => throw new RuntimeException('Unexpected request '.$request->getUri()->getPath()),
                };

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], $json));
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                return new FulfilledPromise($this($request));
            }

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };

        $task = new IndexUpdateTask($connection, 'source', 'dest', 'old', 'new', 2);

        $this->assertSame([
            'source' => 'source',
            'dest' => 'dest',
            'old_alias' => 'old',
            'new_alias' => 'new',
            'requested_replicas' => 2,
            'task' => 'node:1',
        ], $task->pack());

        $unpacked = IndexUpdateTask::unpack($connection, $task->pack());

        $this->assertFalse($unpacked->isCompleted());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Index update is not completed. Tried 1 times.');

        $unpacked->waitAndFinish(maxTries: 0);
    }

    /**
     * @test
     */
    public function index_upsert_creates_new_index_when_none_exists(): void
    {
        $alias = uniqid();

        // Ensure index doesn't exist
        $this->assertNull($this->sigmie->index($alias));

        $index = $this->sigmie->indexUpsert($alias, fn ($builder) => $builder->shards(2)->replicas(1));

        $this->assertInstanceOf(AliasedIndex::class, $index);
        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertShards(2);
            $index->assertReplicas(1);
        });
    }

    /**
     * @test
     */
    public function index_upsert_updates_existing_index(): void
    {
        $alias = uniqid();

        // Create initial index
        $initialIndex = $this->sigmie->newIndex($alias)
            ->shards(1)
            ->replicas(0)
            ->stopwords(['old', 'words'], 'test_stopwords')
            ->create();

        $oldName = $initialIndex->name;

        $index = $this->sigmie->index($alias);

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertShards(1);
            $index->assertReplicas(0);
            $index->assertFilterHasStopwords('test_stopwords', ['old', 'words']);
        });

        // Update through upsert
        $updatedIndex = $this->sigmie->indexUpsert($alias, fn ($builder) => $builder->shards(2)
            ->replicas(1)
            ->stopwords(['new', 'words'], 'test_stopwords'));

        $this->assertInstanceOf(AliasedIndex::class, $updatedIndex);
        $this->assertNotEquals($oldName, $updatedIndex->name);

        $this->assertIndex($alias, function (Assert $index): void {
            $index->assertShards(2);
            $index->assertReplicas(1);
            $index->assertFilterHasStopwords('test_stopwords', ['new', 'words']);
        });
    }

    /**
     * @test
     */
    public function rebuild_hooks_get_the_name_of_the_index_being_rebuilt(): void
    {
        $alias = uniqid();
        $hook = $this->createMock(CollectionHook::class);
        $hook->method('shouldRun')->willReturn(true);
        $hook->method('processBatch')->willReturnArgument(0);
        $hook->expects($this->exactly(2))->method('beforeBatch')->with($alias);
        $hook->expects($this->exactly(2))->method('afterBatch')->with($this->anything(), $alias);
        $this->sigmie->addCollectionHook($hook);

        $this->sigmie->newIndex($alias)->rebuild(function (AliveCollection $docs): void {
            $docs->merge([new Document(['name' => 'Cinderella'])]);
            $docs->add(new Document(['name' => 'Aurora']));
        });

        $this->assertCount(2, $this->sigmie->collect($alias, refresh: true));
    }

    /**
     * @test
     */
    public function rebuild_creates_alias_on_first_build(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->rebuild(fn (AliveCollection $docs): AliveCollection => $docs->merge([new Document(['name' => 'Cinderella'])]));

        $this->assertInstanceOf(AliasedIndex::class, $index);
        $this->assertCount(1, $this->sigmie->collect($alias));
    }

    public function test_rebuild_refuses_a_name_taken_by_an_index_a_write_created(): void
    {
        $name = uniqid();
        $this->sigmie->collect($name, refresh: true)->add(new Document(['name' => 'Cinderella']));
        $filled = false;

        try {
            $this->sigmie->newIndex($name)->rebuild(function (AliveCollection $docs) use (&$filled): void {
                $filled = true;
                $docs->add(new Document(['name' => 'Snow White']));
            });
            $this->fail('The alias name belongs to a concrete index.');
        } catch (AliasAlreadyExists) {
            $this->assertFalse($filled);
            $this->assertNull($this->sigmie->index($name.'_*'));
            $this->assertCount(1, $this->sigmie->collect($name));
        }
    }

    /**
     * @test
     */
    public function rebuild_swaps_alias_and_deletes_old_index(): void
    {
        $alias = uniqid();

        $old = $this->sigmie->newIndex($alias)
            ->rebuild(fn (AliveCollection $docs): AliveCollection => $docs->merge([new Document(['name' => 'Cinderella'])]));

        $new = $this->sigmie->newIndex($alias)
            ->rebuild(fn (AliveCollection $docs): AliveCollection => $docs->merge([
                new Document(['name' => 'Snow White']),
                new Document(['name' => 'Sleeping Beauty']),
            ]));

        $this->assertNotEquals($old->name, $new->name);
        $this->assertIndexNotExists($old->name);
        $this->assertIndexExists($new->name);
        $this->assertCount(2, $this->sigmie->collect($alias));
    }

    /**
     * @test
     */
    public function rebuild_keeps_old_index_live_while_filling(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->rebuild(fn (AliveCollection $docs): AliveCollection => $docs->merge([new Document(['name' => 'Cinderella'])]));

        $this->sigmie->newIndex($alias)
            ->rebuild(function (AliveCollection $docs) use ($alias): void {
                $docs->merge([new Document(['name' => 'Snow White'])]);

                $this->assertCount(1, $this->sigmie->collect($alias));
                $this->assertEquals('Cinderella', array_values($this->sigmie->collect($alias)->toArray())[0]->_source['name']);
            });

        $this->assertEquals('Snow White', array_values($this->sigmie->collect($alias)->toArray())[0]->_source['name']);
    }

    /**
     * @test
     */
    public function rebuild_refuses_empty_index_and_keeps_old_one(): void
    {
        $alias = uniqid();

        $old = $this->sigmie->newIndex($alias)
            ->rebuild(fn (AliveCollection $docs): AliveCollection => $docs->merge([new Document(['name' => 'Cinderella'])]));

        try {
            $this->sigmie->newIndex($alias)->rebuild(function (AliveCollection $docs): void {});
            $this->fail('Expected RebuildTooSmall');
        } catch (RebuildTooSmall) {
        }

        $this->assertIndexExists($old->name);
        $this->assertCount(1, $this->sigmie->collect($alias));
        $this->assertCount(1, $this->sigmie->indices($alias.'_*'));
    }

    /**
     * @test
     */
    public function rebuild_allows_empty_index_when_asked(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->rebuild(function (AliveCollection $docs): void {}, minDocuments: 0);

        $this->assertIndexExists($index->name);
        $this->assertCount(0, $this->sigmie->collect($alias));
    }

    /**
     * @test
     */
    public function rebuild_drops_new_index_when_fill_throws(): void
    {
        $alias = uniqid();

        $old = $this->sigmie->newIndex($alias)
            ->rebuild(fn (AliveCollection $docs): AliveCollection => $docs->merge([new Document(['name' => 'Cinderella'])]));

        try {
            $this->sigmie->newIndex($alias)->rebuild(function (AliveCollection $docs): void {
                $docs->merge([new Document(['name' => 'Snow White'])]);

                throw new RuntimeException('Import failed');
            });
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $runtimeException) {
            $this->assertEquals('Import failed', $runtimeException->getMessage());
        }

        $this->assertIndexExists($old->name);
        $this->assertCount(1, $this->sigmie->collect($alias));
        $this->assertCount(1, $this->sigmie->indices($alias.'_*'));
    }

    /**
     * @test
     */
    public function rebuild_applies_properties_to_new_index(): void
    {
        $alias = uniqid();

        $props = new NewProperties;
        $props->number('year')->integer();

        $this->sigmie->newIndex($alias)
            ->properties($props)
            ->rebuild(fn (AliveCollection $docs): AliveCollection => $docs->merge([new Document(['year' => 1950])]));

        $this->assertIndex($alias, fn (Assert $index) => $index->assertPropertyIsInteger('year'));
    }

    /**
     * @test
     */
    public function rebuild_fills_from_separate_handles_and_finishes_once(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->rebuild(fn (AliveCollection $docs): AliveCollection => $docs->merge([new Document(['name' => 'Cinderella'])]));

        $started = $this->sigmie->newIndex($alias)->replicas(1)->config('index.refresh_interval', '30s')->startRebuild();

        // Each queued job resumes the rebuild by alias alone.
        $this->sigmie->rebuilding($alias)->collect()->merge([new Document(['name' => 'Snow White'])]);
        $this->sigmie->rebuilding($alias)->collect()->merge([new Document(['name' => 'Sleeping Beauty'])]);

        $this->assertEquals($started->name, $this->sigmie->rebuilding($alias)->name);
        $this->assertEquals(2, $this->sigmie->rebuilding($alias)->count());
        $this->assertCount(1, $this->sigmie->collect($alias));

        $index = $this->sigmie->rebuilding($alias)->finish();

        $this->assertEquals($started->name, $index->name);
        $this->assertCount(2, $this->sigmie->collect($alias));
        $this->assertCount(1, $this->sigmie->indices($alias.'_*'));
        $this->assertNull($this->sigmie->rebuilding($alias));
        $this->assertIndex($alias, fn (Assert $index) => $index->assertReplicas(1));
        $this->assertSame('30s', $this->sigmie->index($alias)->settings->toRaw()['refresh_interval']);
    }

    /**
     * @test
     */
    public function rebuilding_returns_null_without_a_pending_rebuild(): void
    {
        $alias = uniqid();

        $this->assertNull($this->sigmie->rebuilding($alias));

        $this->sigmie->newIndex($alias)->create();

        $this->assertNull($this->sigmie->rebuilding($alias));
    }

    /**
     * @test
     */
    public function rebuild_abort_drops_new_index_and_keeps_alias(): void
    {
        $alias = uniqid();

        $old = $this->sigmie->newIndex($alias)
            ->rebuild(fn (AliveCollection $docs): AliveCollection => $docs->merge([new Document(['name' => 'Cinderella'])]));

        $this->sigmie->newIndex($alias)->startRebuild();
        $this->sigmie->rebuilding($alias)->collect()->merge([new Document(['name' => 'Snow White'])]);
        $this->sigmie->rebuilding($alias)->abort();

        $this->assertNull($this->sigmie->rebuilding($alias));
        $this->assertEquals($old->name, $this->sigmie->index($alias)->name);
        $this->assertCount(1, $this->sigmie->indices($alias.'_*'));
    }

    /**
     * @test
     */
    public function rebuild_finish_refuses_fewer_than_min_documents(): void
    {
        $alias = uniqid();

        $old = $this->sigmie->newIndex($alias)
            ->rebuild(fn (AliveCollection $docs): AliveCollection => $docs->merge([new Document(['name' => 'Cinderella'])]));

        $rebuild = $this->sigmie->newIndex($alias)->startRebuild();
        $rebuild->collect()->merge([new Document(['name' => 'Snow White'])]);

        try {
            $rebuild->finish(minDocuments: 2);
            $this->fail('Expected RebuildTooSmall');
        } catch (RebuildTooSmall $rebuildTooSmall) {
            $this->assertStringContainsString('produced 1 documents, fewer than the required 2', $rebuildTooSmall->getMessage());
        }

        $this->assertEquals($old->name, $this->sigmie->index($alias)->name);
        $this->assertNull($this->sigmie->rebuilding($alias));
    }
}
