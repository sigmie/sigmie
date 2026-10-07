<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use RuntimeException;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Document;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\IndexUpdateTask;
use Sigmie\Index\RebuildTooSmall;
use Sigmie\Index\UpdateIndex as Update;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;
use stdClass;

class IndexUpdateTest extends TestCase
{
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

            return $update;
        });

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

            return $update;
        });

        $collection = $this->sigmie->collect($alias, true);

        $this->assertNotEquals($oldIndexName, $updatedIndex->name);
        $this->assertCount(10, $collection);
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

        $connection = new class($alias) implements ElasticsearchConnection
        {
            public array $settingsCalls = [];

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $path = $request->getUri()->getPath();
                $method = $request->getMethod();
                $body = json_decode((string) $request->getBody(), true) ?? [];

                if ($method === 'PUT' && str_ends_with($path, '/_settings')) {
                    $this->settingsCalls[] = $body;

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($method === 'PUT') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($method === 'POST') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($method === 'DELETE') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($method === 'GET' && ! str_starts_with($path, '/_resolve')) {
                    $aliasName = ltrim($path, '/');
                    $data = [
                        $aliasName.'_concrete' => [
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

        $connection = new class($dest, $alias) implements ElasticsearchConnection
        {
            public array $settingsCalls = [];

            public function __construct(private string $dest) {}

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $path = $request->getUri()->getPath();
                $method = $request->getMethod();

                if ($method === 'PUT' && str_ends_with($path, '/_settings')) {
                    $this->settingsCalls[] = json_decode((string) $request->getBody(), true) ?? [];

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($method === 'POST') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"task":"node:1","acknowledged":true}'));
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
                if ($request->getMethod() === 'POST') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"task":"node:1"}'));
                }

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"nodes":{"node":{"tasks":{"node:1":{}}}}}'));
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
    public function rebuild_creates_alias_on_first_build(): void
    {
        $alias = uniqid();

        $index = $this->sigmie->newIndex($alias)
            ->rebuild(fn (AliveCollection $docs): AliveCollection => $docs->merge([new Document(['name' => 'Cinderella'])]));

        $this->assertInstanceOf(AliasedIndex::class, $index);
        $this->assertCount(1, $this->sigmie->collect($alias));
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

        $started = $this->sigmie->newIndex($alias)->replicas(1)->startRebuild();

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
