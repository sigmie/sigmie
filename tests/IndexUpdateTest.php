<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Document\Document;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\IndexUpdateTask;
use Sigmie\Index\UpdateIndex as Update;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;

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

        $connection = new class ($alias) implements ElasticsearchConnection {
            public array $settingsCalls = [];

            public function __construct(private string $alias) {}

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
                            'aliases' => [$aliasName => new \stdClass],
                            'mappings' => ['properties' => new \stdClass],
                            'settings' => ['index' => ['number_of_shards' => '1', 'number_of_replicas' => '0']],
                        ],
                    ];

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) json_encode($data)));
                }

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"indices":[]}'));
            }

            public function promise(ElasticsearchRequest $request): Promise { return new FulfilledPromise($this($request)); }

            public function driver(): SearchEngine { return new Elasticsearch; }

            public function isServerless(): bool { return true; }
        };

        $index = new AliasedIndex($sourceName, $alias);
        $index->setElasticsearchConnection($connection);

        $index->update(fn (Update $update) => $update);

        $afterReindex = array_values(array_filter(
            $connection->settingsCalls,
            fn ($s) => isset($s['refresh_interval'])
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

        $connection = new class ($dest, $alias) implements ElasticsearchConnection {
            public array $settingsCalls = [];

            public function __construct(private string $dest, private string $alias) {}

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
                            'aliases' => [$aliasName => new \stdClass],
                            'mappings' => ['properties' => new \stdClass],
                            'settings' => ['index' => ['number_of_shards' => '1', 'number_of_replicas' => '0']],
                        ],
                    ];

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) json_encode($data)));
                }

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"indices":[]}'));
            }

            public function promise(ElasticsearchRequest $request): Promise { return new FulfilledPromise($this($request)); }

            public function driver(): SearchEngine { return new Elasticsearch; }

            public function isServerless(): bool { return true; }
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
}
