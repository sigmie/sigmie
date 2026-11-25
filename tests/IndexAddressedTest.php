<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Document\Document;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Index\Alias\Actions as AliasActions;
use Sigmie\Index\Alias\AliasAlreadyExists;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\BuiltInAnalyzer;
use Sigmie\Index\Analysis\Tokenizers\Noop;
use Sigmie\Index\Contracts\Language;
use Sigmie\Index\Index;
use Sigmie\Index\IndexRebuild;
use Sigmie\Index\IndexTemplate;
use Sigmie\Index\IndexUpdateTask;
use Sigmie\Index\ListedIndex;
use Sigmie\Index\Mappings;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Index\NewIndex;
use Sigmie\Index\RebuildLost;
use Sigmie\Index\Settings;
use Sigmie\Index\UpdateIndex;
use Sigmie\Languages\English\English;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Sigmie;
use stdClass;
use Throwable;

class IndexAddressedTest extends TestCase
{
    public function test_human_readable_creation_date_is_not_replayed_as_an_index_setting(): void
    {
        $settings = Settings::fromRaw(['settings' => ['index' => [
            'creation_date_string' => '2026-11-24T14:24:57.719Z',
            'refresh_interval' => '30s',
        ]]])->toRaw();

        $this->assertArrayNotHasKey('creation_date_string', $settings);
        $this->assertSame('30s', $settings['refresh_interval']);
    }

    public function test_restored_default_pipeline_can_be_reconfigured(): void
    {
        $settings = Settings::fromRaw(['index' => ['default_pipeline' => 'first']]);
        $settings->config('default_pipeline', 'second');

        $this->assertSame('second', $settings->toRaw()['default_pipeline']);
    }

    public function test_bulk_index_creation_respects_required_active_shard_copies(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponse {
            if ($request->getMethod() === 'HEAD') {
                return $request->response(new PsrResponse(404));
            }

            $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertGreaterThanOrEqual(1, $body['settings']['number_of_replicas']);

            return $request->response(new PsrResponse(200, [], '{"acknowledged":true}'));
        });

        (new NewIndex($connection))->alias('contract-shards')->replicas(2)
            ->config('index', ['write' => ['wait_for_active_shards' => '2']])->startRebuild();
    }

    public function test_make_preserves_a_replacement_default_analyzer(): void
    {
        $builder = (new NewIndex($this->connection()))->alias('logs');
        $custom = new Analyzer('default', new Noop('whole'));
        $builder->analysis()->addAnalyzer($custom);
        $builder->lowercase('words');

        $this->assertSame($custom->toRaw(), $builder->make()->settings->analysis()->defaultAnalyzer()->toRaw());
        $this->assertSame(['words'], $builder->make()->settings->analysis()->toRaw()['analyzer']['default']['filter']);

        $builtIn = new BuiltInAnalyzer('default', ['type' => 'english']);
        $builder->analysis()->addAnalyzer($builtIn);
        $this->assertSame($builtIn->toRaw(), $builder->make()->settings->analysis()->defaultAnalyzer()->toRaw());
    }

    public function test_index_restore_resolves_an_explicit_analyzer_named_standard_or_simple(): void
    {
        $raw = [
            'settings' => ['index' => ['analysis' => ['analyzer' => [
                'standard' => ['type' => 'keyword'],
                'simple' => ['type' => 'whitespace'],
            ]]]],
            'mappings' => ['properties' => [
                'exact' => ['type' => 'text', 'analyzer' => 'standard'],
                'words' => ['type' => 'text', 'analyzer' => 'simple'],
            ]],
        ];
        $fields = Index::fromRaw('logs', $raw)->mappings->properties();

        $this->assertSame(['standard' => ['type' => 'keyword']], $fields->get('exact')->analyzer()->toRaw());
        $this->assertSame(['simple' => ['type' => 'whitespace']], $fields->get('words')->analyzer()->toRaw());
    }

    /** @dataProvider aliasCreationInterleavings */
    public function test_create_if_not_exists_returns_an_alias_created_between_checks(bool $createdBeforePut): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use ($createdBeforePut): ElasticsearchResponse {
            $path = $request->getUri()->getPath();
            $body = ['live' => ['aliases' => ['movies' => []], 'settings' => [], 'mappings' => []]];
            if ($request->getMethod() === 'PUT' && str_starts_with($path, '/movies_')) {
                throw new ElasticsearchException([
                    'type' => 'illegal_state_exception', 'reason' => 'alias [movies] has more than one write index',
                ], 500);
            }

            $response = match ($request->getMethod().' '.$path) {
                'HEAD /_alias/movies' => new PsrResponse(404),
                'HEAD /movies' => new PsrResponse($createdBeforePut ? 200 : 404),
                'GET /movies' => new PsrResponse(200, [], json_encode($body, JSON_THROW_ON_ERROR)),
                default => throw new RuntimeException('Unexpected request '.$path),
            };

            return $request->response($response);
        });

        $this->assertSame('live', (new Sigmie($connection))->newIndex('movies')->createIfNotExists()->name);
    }

    public static function aliasCreationInterleavings(): array
    {
        return [[true], [false]];
    }

    public function test_analyzer_named_for_the_largest_integer_keeps_the_default(): void
    {
        $name = (string) PHP_INT_MAX;
        $props = new NewProperties;
        $props->text('title')->withNewAnalyzer(fn (NewAnalyzer $analyzer): NewAnalyzer => $analyzer->name($name));
        $index = (new NewIndex($this->connection()))->alias('books')->properties($props)->make();
        $analyzers = $index->settings->analysis()->analyzers();
        $this->assertArrayHasKey($name, $analyzers);
        $this->assertArrayHasKey('default', $analyzers);
    }

    public function test_unprefixed_setting_overrides_the_engine_default(): void
    {
        $settings = Sigmie::create('localhost:9200', SearchEngineType::OpenSearch)
            ->newIndex('books')->config('knn', false)->make()->settings->toRaw();

        $this->assertFalse($settings['knn']);
        $this->assertArrayNotHasKey('index.knn', $settings);
    }

    public function test_language_selection_preserves_the_index_definition(): void
    {
        $props = new NewProperties;
        $props->keyword('isbn');

        $index = (new NewIndex($this->connection()))->alias('books')
            ->properties($props)->meta(['edition' => 2])->config('refresh_interval', '30s')
            ->lowercase('fold')->searchSynonyms(['book, volume'])
            ->language(new English)->englishStemmer()->make();

        $settings = $index->settings->toRaw();
        $this->assertSame('30s', $settings['refresh_interval'] ?? null);
        $this->assertSame(2, $index->mappings->meta()['edition']);
        $this->assertSame(['isbn'], $index->mappings->fieldNames());
        $this->assertSame(['fold', 'english_stemmer'], $settings['analysis']['analyzer']['default']['filter']);
        $this->assertArrayHasKey('default_with_synonyms', $settings['analysis']['analyzer']);
    }

    public function test_made_index_owns_its_mapping_properties(): void
    {
        $props = new NewProperties;
        $props->keyword('isbn');

        $builder = (new NewIndex($this->connection()))->alias('books')->properties($props);
        $first = $builder->make();
        unset($first->mappings->properties()['isbn']);
        $this->assertSame(['isbn'], $builder->make()->mappings->fieldNames());
    }

    public function test_nested_field_analyzers_are_exposed_by_the_mapping(): void
    {
        $props = new NewProperties;
        $props->object('book', fn (NewProperties $book): Text => $book->title('title'));

        $index = (new NewIndex($this->connection()))->alias('books')->properties($props)->make();
        $analyzer = $index->mappings->properties()->get('book.title')->analyzer();
        $this->assertContains($analyzer, $index->mappings->analyzers());
    }

    public function test_scalar_embeddings_mapping_survives_a_round_trip(): void
    {
        $mapping = Mappings::create(['properties' => ['_embeddings' => ['type' => 'keyword']]], []);
        $field = $mapping->toRaw(new Elasticsearch)['properties']->_embeddings;
        $this->assertSame('keyword', $field['type']);
        $this->assertArrayNotHasKey('properties', $field);
    }

    public function test_finished_update_rejects_a_subsequent_alias_target(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponse {
            $path = $request->getUri()->getPath();
            if (str_starts_with($path, '/source/_alias/')) {
                return $request->response(new PsrResponse(404));
            }

            $target = $path === '/books' ? 'next' : 'dest';
            $body = [$target => ['aliases' => ['books' => []], 'mappings' => [], 'settings' => []]];

            return $request->response(new PsrResponse(200, [], json_encode($body, JSON_THROW_ON_ERROR)));
        });
        $task = new IndexUpdateTask($connection, 'source', 'dest', 'books', 'books', 0, task: 'node:1');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Alias 'books' no longer points to 'dest'.");
        $task->finish();
    }

    public function test_cutover_refuses_an_unexpected_alias_target(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->expects($this->once())->method('__invoke')
            ->willReturnCallback(fn (ElasticsearchRequest $request): ElasticsearchResponse => $request->response(new PsrResponse(
                200, [], '{"original":{"aliases":{"movies":{}}},"additional":{"aliases":{"movies":{}}}}',
            )));
        $client = new class
        {
            use AliasActions { moveAlias as public; }
        };
        $client->setElasticsearchConnection($connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Alias 'movies' also points to 'additional'");
        $client->moveAlias('movies', ['original'], 'replacement');
    }

    public function test_alias_cutover_accepts_the_same_filter_with_different_json_key_order(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->expects($this->exactly(2))->method('__invoke')
            ->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponse {
                if ($request->getMethod() === 'GET') {
                    return $request->response(new PsrResponse(200, [], '{"one":{"aliases":{"movies":{"filter":{"range":{"price":{"gt":10,"lt":50}}}}}},"two":{"aliases":{"movies":{"filter":{"range":{"price":{"lt":50,"gt":10}}}}}}}'));
                }

                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertEquals(['range' => ['price' => ['gt' => 10, 'lt' => 50]]], $body['actions'][2]['add']['filter']);

                return $request->response(new PsrResponse(200, [], '{"acknowledged":true}'));
            });
        $client = new class
        {
            use AliasActions { moveAlias as public; }
        };
        $client->setElasticsearchConnection($connection);

        $client->moveAlias('movies', ['one', 'two'], 'new');
    }

    public function test_collection_from_an_aliased_index_requires_the_alias_for_writes(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->expects($this->once())->method('__invoke')
            ->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponse {
                parse_str($request->getUri()->getQuery(), $query);
                $this->assertSame('/movies/_bulk', $request->getUri()->getPath());
                $this->assertSame('true', $query['require_alias'] ?? null);

                return $request->response(new PsrResponse(200, [], '{"items":[{"create":{"_index":"movies_1","_id":"1","status":201}}]}'));
            });
        $connection->method('driver')->willReturn(new Elasticsearch);
        $index = new AliasedIndex('movies_1', 'movies');
        $index->setElasticsearchConnection($connection);

        $this->assertSame('movies_1', $index->collect()->add(new Document(['title' => 'Solaris'], '1'))->_index);
    }

    public function test_retry_after_lost_delete_response_preserves_recreated_source(): void
    {
        $deletes = 0;
        $dest = '';
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use (&$deletes, &$dest): ElasticsearchResponse {
            $path = $request->getUri()->getPath();
            if ($request->getMethod() === 'PUT' && ! str_contains($path, '/_')) {
                $dest = ltrim($path, '/');
            }

            if ($request->getMethod() === 'DELETE' && ++$deletes === 1) {
                throw new RuntimeException('Lost delete response');
            }

            if (str_ends_with($path, '/_block/write')) {
                return $request->response(new PsrResponse(200, [], '{"shards_acknowledged":true}'));
            }

            $body = match ($path) {
                '/movies' => [$dest => ['aliases' => ['movies' => []], 'settings' => ['index' => []], 'mappings' => []]],
                '/_reindex' => ['took' => 1, 'total' => 0, 'created' => 0, 'timed_out' => false, 'failures' => []],
                '/_alias/movies' => ['movies_old' => ['aliases' => ['movies' => []]]],
                default => ['acknowledged' => true],
            };
            $status = $request->getMethod() === 'HEAD' ? 404 : 200;

            return $request->response(new PsrResponse($status, [], json_encode($body)));
        });
        $source = new AliasedIndex('movies_old', 'movies');
        $source->setElasticsearchConnection($connection);

        $configure = fn (UpdateIndex $update): UpdateIndex => $update;
        try {
            $source->update($configure);
            $this->fail('Expected the delete response to be lost.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('Lost delete response', $runtimeException->getMessage());
        }

        $this->assertSame($dest, $source->update($configure)->name);
        $this->assertSame(1, $deletes);
    }

    public function test_sync_update_detects_a_later_alias_cutover(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponse {
            $path = $request->getUri()->getPath();
            if (str_ends_with($path, '/_block/write')) {
                return $request->response(new PsrResponse(200, [], '{"shards_acknowledged":true}'));
            }

            $body = match ($path) {
                '/movies' => ['later' => ['aliases' => ['movies' => []], 'settings' => ['index' => []], 'mappings' => []]],
                '/_reindex' => ['took' => 1, 'total' => 0, 'created' => 0, 'timed_out' => false, 'failures' => []],
                '/_alias/movies' => ['movies_old' => ['aliases' => ['movies' => []]]],
                default => ['acknowledged' => true],
            };

            return $request->response(new PsrResponse(200, [], json_encode($body)));
        });
        $index = new AliasedIndex('movies_old', 'movies');
        $index->setElasticsearchConnection($connection);
        $this->expectException(RebuildLost::class);
        $index->update(fn (UpdateIndex $update): UpdateIndex => $update);
    }

    public function test_language_builder_accepts_an_alias_after_language_selection(): void
    {
        $builder = (new NewIndex($this->connection()))
            ->language(new English)
            ->englishStemmer()
            ->alias('articles');

        $this->assertSame('articles', $builder->getAlias());
        $this->assertSame(['english_stemmer'], $builder->make()->settings->toRaw()['analysis']['analyzer']['default']['filter']);
    }

    public function test_audit_serverless_rebuild_does_not_restore_replicas(): void
    {
        $requests = [];
        $raw = [];
        $name = '';
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use (&$requests, &$raw, &$name): ElasticsearchResponse {
            $requests[] = $request;
            $path = $request->getUri()->getPath();
            $method = $request->getMethod();
            $status = $method === 'HEAD' ? 404 : 200;
            $body = ['acknowledged' => true];

            if ($method === 'PUT' && $name === '') {
                $name = ltrim($path, '/');
                $raw = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
            }

            if ($method === 'GET' && $path === '/movies_*') {
                $body = [$name => $raw];
            }

            if ($method === 'GET' && $path === '/movies') {
                $body = [$name => ['aliases' => ['movies' => []], 'settings' => ['index' => []], 'mappings' => $raw['mappings']]];
            }

            if (str_ends_with($path, '/_count')) {
                $body = ['count' => 0];
            }

            return $request->response(new PsrResponse($status, [], json_encode($body, JSON_THROW_ON_ERROR)));
        });
        $sigmie = new Sigmie($connection);
        $sigmie->newIndex('movies')->replicas(3)->serverless()->startRebuild();
        $sigmie->rebuilding('movies')->finish(minDocuments: 0);

        $settingsWrites = array_filter($requests, fn (ElasticsearchRequest $request): bool => str_ends_with($request->getUri()->getPath(), '/_settings'));
        $this->assertCount(1, $settingsWrites);
        $settings = json_decode((string) reset($settingsWrites)->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('number_of_replicas', $settings);
    }

    public function test_audit_index_create_rejects_missing_acknowledgement(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponse {
            $status = $request->getMethod() === 'HEAD' ? 404 : 200;

            return $request->response(new PsrResponse($status, [], '{"acknowledged":false}'));
        });

        $this->expectException(ElasticsearchException::class);
        (new Sigmie($connection))->newIndex('movies')->create();
    }

    public function test_audit_alias_creation_rejects_missing_acknowledgement(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('__invoke')->willReturnCallback(
            fn (ElasticsearchRequest $request): ElasticsearchResponse => $request->response(new PsrResponse(200, [], '{"acknowledged":false}')),
        );
        $client = new class
        {
            use AliasActions { createAlias as public; }
        };
        $client->setElasticsearchConnection($connection);

        $this->expectException(ElasticsearchException::class);
        $client->createAlias('movies_20261124', 'movies');
    }

    public function test_audit_alias_move_keeps_a_multi_index_alias_read_only(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponse {
            if ($request->getMethod() === 'GET') {
                return $request->response(new PsrResponse(200, [], '{"old1":{"aliases":{"movies":{}}},"old2":{"aliases":{"movies":{}}}}'));
            }

            $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(false, $body['actions'][2]['add']['is_write_index']);

            return $request->response(new PsrResponse(200, [], '{"acknowledged":true}'));
        });

        $client = new class
        {
            use AliasActions { moveAlias as public; }
        };
        $client->setElasticsearchConnection($connection);
        $client->moveAlias('movies', ['old1', 'old2'], 'new');
    }

    public function test_audit_config_honors_explicit_shard_and_replica_counts(): void
    {
        $builder = (new Sigmie($this->connection()))->newIndex('movies')
            ->config('index.number_of_shards', '3')
            ->config('number_of_replicas', 0);

        $settings = $builder->make()->settings;

        $this->assertSame(3, $settings->primaryShards());
        $this->assertSame(0, $settings->replicaShards());
        $this->assertSame(3, $settings->toRaw()['number_of_shards']);
        $this->assertSame(0, $settings->toRaw()['number_of_replicas']);
        $this->assertArrayNotHasKey('index.number_of_shards', $settings->toRaw());
        $this->assertSame(2, $builder->shards(2)->make()->settings->primaryShards());
        $this->assertArrayNotHasKey('number_of_shards', $builder->serverless()->make()->settings->toRaw());
    }

    public function test_audit_rebuild_uses_nested_refresh_configuration(): void
    {
        $requests = [];
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use (&$requests): ElasticsearchResponse {
            $requests[] = $request;
            $status = $request->getMethod() === 'HEAD' ? 404 : 200;

            return $request->response(new PsrResponse($status, [], '{"acknowledged":true}'));
        });
        $builder = (new Sigmie($connection))->newIndex('movies')
            ->config('index', ['refresh_interval' => '30s', 'gc_deletes' => null, 'max_ngram_diff' => 3]);

        $builder->startRebuild();

        $body = json_decode((string) $requests[1]->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('30s', $body['mappings']['_meta'][IndexRebuild::META_REFRESH]);
        $this->assertSame('-1', $body['settings']['refresh_interval']);
        $this->assertArrayHasKey('index.gc_deletes', $body['settings']);
        $this->assertNull($body['settings']['index.gc_deletes']);
        $this->assertSame('30s', $builder->make()->settings->toRaw()['refresh_interval']);
    }

    public function test_audit_index_config_keeps_a_fractional_merge_setting(): void
    {
        $settings = (new Sigmie($this->connection()))->newIndex('movies')
            ->config('index.merge.policy.deletes_pct_allowed', 23.5)
            ->make()->settings->toRaw();

        $this->assertSame(23.5, $settings['index.merge.policy.deletes_pct_allowed']);
    }

    public function test_audit_settings_config_keeps_list_values(): void
    {
        $settings = (new Settings)->config('index.sort.field', ['genre', 'title']);

        $this->assertSame(['genre', 'title'], $settings->toRaw()['index.sort.field']);
    }

    public function test_audit_config_can_override_the_engine_default(): void
    {
        $settings = Sigmie::create('localhost:9200', SearchEngineType::OpenSearch)
            ->newIndex('movies')->config('index.knn', false)->make()->settings->toRaw();

        $this->assertFalse($settings['index.knn']);
    }

    public function test_audit_settings_read_flat_settings_from_elasticsearch(): void
    {
        $settings = Settings::fromRaw(['settings' => [
            'index.number_of_shards' => '3',
            'index.number_of_replicas' => '0',
            'index.default_pipeline' => 'ingest',
            'index.refresh_interval' => '30s',
            'index.routing.allocation.enable' => 'primaries',
            'index.uuid' => 'internal',
        ]]);

        $this->assertSame(3, $settings->primaryShards());
        $this->assertSame(0, $settings->replicaShards());
        $this->assertSame('ingest', $settings->toRaw()['default_pipeline']);
        $this->assertSame('30s', $settings->toRaw()['refresh_interval']);
        $this->assertSame('primaries', $settings->toRaw()['routing']['allocation']['enable']);
        $this->assertArrayNotHasKey('uuid', $settings->toRaw());
    }

    public function test_settings_restore_their_own_raw_payload(): void
    {
        $settings = new Settings(3, 0);
        $settings->defaultPipeline('ingest')->config('refresh_interval', '30s');

        $restored = Settings::fromRaw($settings->toRaw());

        $this->assertSame(3, $restored->primaryShards());
        $this->assertSame(0, $restored->replicaShards());
        $this->assertSame($settings->toRaw(), $restored->toRaw());
    }

    public function test_settings_restore_mixed_prefixed_and_unprefixed_keys(): void
    {
        $settings = Settings::fromRaw(['settings' => [
            'index.number_of_replicas' => 0,
            'refresh_interval' => '30s',
            'analysis' => ['analyzer' => ['words' => ['type' => 'whitespace']]],
        ]]);

        $this->assertSame(0, $settings->replicaShards());
        $this->assertSame('30s', $settings->toRaw()['refresh_interval']);
        $this->assertSame(['type' => 'whitespace'], $settings->toRaw()['analysis']['analyzer']['words']);
    }

    public function test_audit_mapping_round_trip_keeps_dynamic_rules(): void
    {
        $raw = [
            'dynamic' => 'strict',
            '_routing' => ['required' => true],
            'dynamic_templates' => [
                ['identifiers' => ['match' => '*_id', 'mapping' => ['type' => 'keyword']]],
            ],
            'date_detection' => false,
        ];

        $serialized = Index::fromRaw('movies', [
            'mappings' => $raw + ['runtime' => ['0' => ['type' => 'long']]],
        ])->mappings->toRaw(new Elasticsearch);

        foreach ($raw as $name => $value) {
            $this->assertArrayHasKey($name, $serialized);
            $this->assertSame($value, $serialized[$name]);
        }

        $this->assertSame('{"0":{"type":"long"}}', json_encode($serialized['runtime']));
    }

    public function test_audit_index_template_keeps_the_payload_it_was_read_from(): void
    {
        $raw = [
            'order' => 3,
            'index_patterns' => ['movies-*'],
            'settings' => ['index' => ['number_of_replicas' => '0']],
            'aliases' => ['movies' => []],
        ];

        $this->assertSame($raw, IndexTemplate::fromRaw('movies', $raw)->raw);
    }

    public function test_audit_list_indices_reads_dataset_size_from_the_cat_api(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponse {
            $row = [
                'index' => 'movies', 'health' => 'green', 'status' => 'open', 'uuid' => 'es-uuid',
                'pri' => '1', 'rep' => '0', 'docs.count' => '0', 'docs.deleted' => '0',
                'store.size' => '5mb', 'pri.store.size' => '5mb',
            ];
            parse_str($request->getUri()->getQuery(), $query);
            if (in_array('dataset.size', explode(',', $query['h'] ?? ''), true)) {
                $row['dataset.size'] = '20mb';
            }

            $body = str_starts_with($request->getUri()->getPath(), '/_cat/indices') ? [$row] : [];

            return $request->response(new PsrResponse(200, [], json_encode($body, JSON_THROW_ON_ERROR)));
        });

        [$listed] = (new Sigmie($connection))->indices('movies');

        $this->assertSame('20mb', $listed->datasetSize);
        $this->assertSame('5mb', $listed->primaryStoreSize);
    }

    public function test_audit_index_template_save_accepts_filtered_pattern_lists(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->expects($this->once())->method('__invoke')
            ->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponse {
                $body = json_decode((string) $request->getBody(), flags: JSON_THROW_ON_ERROR);
                $this->assertSame(['movies-*'], $body->index_patterns);

                return $request->response(new PsrResponse(200, [], '{"acknowledged":true}'));
            });

        $patterns = array_filter(['', 'movies-*']);
        $template = (new Sigmie($connection))->newIndex('movies')->save('movies', $patterns);

        $this->assertSame(['movies-*'], $template->patterns);
    }

    public function test_audit_index_template_save_rejects_missing_acknowledgement(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(
            fn (ElasticsearchRequest $request): ElasticsearchResponse => $request->response(new PsrResponse(200, [], '{"acknowledged":false}')),
        );

        $this->expectException(RuntimeException::class);
        (new Sigmie($connection))->newIndex('movies')->save('movies', ['movies-*']);
    }

    public function test_audit_index_decodes_the_settings_envelope_from_the_get_api(): void
    {
        $raw = [
            'aliases' => ['movies' => []],
            'mappings' => ['properties' => ['title' => ['type' => 'text']]],
            'settings' => ['index' => ['number_of_replicas' => '1']],
        ];

        $index = Index::fromRaw('movies_20261124', $raw);
        $aliased = AliasedIndex::fromRaw('movies_20261124', $raw);
        $template = IndexTemplate::fromRaw('movies', $raw + ['index_patterns' => ['movies-*']]);

        $this->assertSame(1, $index->settings->replicaShards());
        $this->assertSame(1, $aliased->settings->replicaShards());
        $this->assertSame(1, $template->settings->replicaShards());
    }

    public function test_audit_index_from_raw_keeps_the_inspectable_payload(): void
    {
        $raw = [
            'aliases' => ['movies' => []],
            'mappings' => [],
            'index' => ['number_of_shards' => '2'],
        ];

        $this->assertSame($raw, Index::fromRaw('movies_20261124', $raw)->raw);
        $this->assertSame('2', AliasedIndex::fromRaw('movies_20261124', $raw)->get('index.number_of_shards'));
    }

    public function test_switching_index_connections_uses_the_new_engines_settings(): void
    {
        $builder = Sigmie::create('localhost:9200', SearchEngineType::OpenSearch)->newIndex('movies');
        $this->assertTrue($builder->make()->settings->toRaw()['index.knn']);

        $builder->setElasticsearchConnection($this->connection());
        $this->assertArrayNotHasKey('index.knn', $builder->make()->settings->toRaw());
    }

    /** @test */
    public function making_another_index_does_not_change_the_first_indexes_analysis(): void
    {
        $builder = (new NewIndex($this->connection()))->alias('snapshot')->lowercase('lower');
        $first = $builder->make();
        $before = $first->settings->toRaw();

        $builder->trim('trim_whitespace');
        $second = $builder->make();

        $this->assertSame(['lower', 'trim_whitespace'], $second->settings->toRaw()['analysis']['analyzer']['default']['filter']);
        $this->assertSame($before, $first->settings->toRaw());
    }

    /**
     * @test
     */
    public function a_field_analyzer_keeps_only_its_own_filters(): void
    {
        $properties = new NewProperties;
        $properties->email('email');
        $properties->text('note')->withNewAnalyzer(function ($analyzer): void {
            $analyzer->lowercase('note_lower');
        });

        $analyzers = (new NewIndex($this->connection()))
            ->alias('movies')
            ->lowercase('index_lower')
            ->stopwords(['the', 'a'], 'index_stop')
            ->properties($properties)
            ->make()
            ->settings
            ->analysis()
            ->toRaw()['analyzer'];

        $email = $analyzers['email_field_analyzer']['filter'];

        $this->assertSame(['note_lower'], $analyzers['note_field_analyzer']['filter']);
        $this->assertCount(1, $email);
        $this->assertNotContains('index_lower', $email);
        $this->assertNotContains('index_stop', $email);
        $this->assertSame(['index_lower', 'index_stop'], $analyzers['default']['filter']);
    }

    /**
     * @test
     */
    public function a_dotted_field_path_keeps_its_own_analyzer(): void
    {
        $properties = new NewProperties;
        $properties->object('author', function (NewProperties $properties): void {
            $properties->text('bio')->withNewAnalyzer(function ($analyzer): void {
                $analyzer->lowercase('bio_lower');
            });
        });
        $properties->text('author_bio')->withNewAnalyzer(function ($analyzer): void {
            $analyzer->uppercase('bio_upper');
        });

        $made = (new NewIndex($this->connection()))
            ->alias('movies')
            ->properties($properties)
            ->make();

        $analyzers = $made->settings->analysis()->toRaw()['analyzer'];
        $mappings = json_decode(json_encode($made->mappings->toRaw(new Elasticsearch)), true);
        $bio = $made->mappings->properties()->get('author.bio');
        $flat = $made->mappings->properties()->get('author_bio');

        $this->assertSame(['bio_lower'], $analyzers['author%2Ebio_field_analyzer']['filter']);
        $this->assertSame(['bio_upper'], $analyzers['author_bio_field_analyzer']['filter']);
        $this->assertSame('author%2Ebio_field_analyzer', $mappings['properties']['author']['properties']['bio']['analyzer']);
        $this->assertSame('author_bio_field_analyzer', $mappings['properties']['author_bio']['analyzer']);
        $this->assertSame('author%2Ebio_field_analyzer', $bio->queries('x')[0]->toRaw()['match']['author.bio']['analyzer']);
        $this->assertSame('author_bio_field_analyzer', $flat->queries('x')[0]->toRaw()['match']['author_bio']['analyzer']);
    }

    /**
     * @test
     */
    public function a_subfield_analyzer_keeps_its_parent_path(): void
    {
        $properties = new NewProperties;
        $properties->text('title')->field(
            (new Text('exact'))->unstructuredText()->withNewAnalyzer(function (NewAnalyzer $analyzer): void {
                $analyzer->lowercase('title_exact_lower');
            })
        );
        $properties->text('headline')->field(
            (new Text('exact'))->unstructuredText()->withNewAnalyzer(function (NewAnalyzer $analyzer): void {
                $analyzer->uppercase('headline_exact_upper');
            })
        );

        $made = (new NewIndex($this->connection()))
            ->alias('movies')
            ->properties($properties)
            ->make();

        $analyzers = $made->settings->analysis()->toRaw()['analyzer'];
        $mappings = json_decode(json_encode($made->mappings->toRaw(new Elasticsearch)), true);
        $subfield = function (string $name) use ($made): Text {
            $property = new ReflectionProperty(Text::class, 'fields');

            foreach ($property->getValue($made->mappings->properties()->get($name)) as $sub) {
                if ($sub instanceof Text) {
                    return $sub;
                }
            }

            throw new RuntimeException('missing subfield');
        };
        $title = $subfield('title');
        $headline = $subfield('headline');

        $this->assertSame(['title_exact_lower'], $analyzers['title%2Eexact_field_analyzer']['filter']);
        $this->assertSame(['headline_exact_upper'], $analyzers['headline%2Eexact_field_analyzer']['filter']);
        $this->assertSame('title%2Eexact_field_analyzer', $mappings['properties']['title']['fields']['exact']['analyzer']);
        $this->assertSame('headline%2Eexact_field_analyzer', $mappings['properties']['headline']['fields']['exact']['analyzer']);
        $this->assertSame('title%2Eexact_field_analyzer', $title->queries('x')[0]->toRaw()['match']['title.exact']['analyzer']);
        $this->assertSame('headline%2Eexact_field_analyzer', $headline->queries('x')[0]->toRaw()['match']['headline.exact']['analyzer']);
    }

    /**
     * @test
     */
    public function search_synonyms_follow_lowercase_and_folding(): void
    {
        $analyzers = (new NewIndex($this->connection()))
            ->alias('movies')
            ->lowercase('lower')
            ->asciiFolding('fold')
            ->searchSynonyms(['ipod, i pod'])
            ->make()
            ->settings
            ->analysis()
            ->toRaw()['analyzer'];

        $this->assertSame(['lower', 'fold'], $analyzers['default']['filter']);
        $this->assertSame(
            ['lower', 'fold', 'search_synonyms'],
            $analyzers['default_with_synonyms']['filter'],
        );
    }

    /**
     * @test
     */
    public function settings_from_raw_keeps_the_pipeline_and_config(): void
    {
        $written = (new Settings(primaryShards: 1, replicaShards: 0))
            ->defaultPipeline('ingest')
            ->config('refresh_interval', '30s')
            ->config('max_ngram_diff', '3')
            ->toRaw();

        $this->assertSame($written, Settings::fromRaw(['index' => $written])->toRaw());

        $zero = (new Settings)->defaultPipeline('0')->toRaw();

        $this->assertSame('0', Settings::fromRaw(['index' => $zero])->toRaw()['default_pipeline']);
    }

    /**
     * @test
     */
    public function aliased_index_get_returns_a_leaf_and_a_missing_path(): void
    {
        $index = new AliasedIndex('movies_1', 'movies', raw: [
            'settings' => ['index' => ['number_of_shards' => '1']],
            'mappings' => ['properties' => ['title' => ['type' => 'text']]],
        ]);

        $this->assertSame('1', $index->get('settings.index.number_of_shards'));
        $this->assertNull($index->get('missing'));
        $this->assertSame(
            ['properties' => ['title' => ['type' => 'text']]],
            $index->get('mappings'),
        );
    }

    /**
     * @test
     */
    public function start_rebuild_restores_the_configured_replicas(): void
    {
        $created = [];
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use (&$created): ElasticsearchResponse {
            if ($request->getMethod() === 'PUT') {
                $created[$request->getUri()->getPath()] = json_decode((string) $request->getBody(), true);

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
            }

            return $request->response(new PsrResponse(404, ['Content-Type' => 'application/json'], '{}'));
        });

        $builder = (new NewIndex($connection))->alias('movies')->replicas(2);
        $before = $builder->make()->settings->toRaw();

        $first = $builder->startRebuild();
        $second = $builder->startRebuild();
        $after = $builder->make()->settings->toRaw();

        $this->assertSame(2, $before['number_of_replicas']);
        $this->assertArrayNotHasKey('refresh_interval', $before);
        foreach ([$first, $second] as $rebuild) {
            $payload = $created['/'.$rebuild->name];
            $this->assertSame(0, $payload['settings']['number_of_replicas']);
            $this->assertSame('-1', $payload['settings']['refresh_interval']);
            $this->assertSame(2, $payload['mappings']['_meta'][IndexRebuild::META_REPLICAS]);
        }

        $this->assertSame(2, $after['number_of_replicas']);
        $this->assertArrayNotHasKey('refresh_interval', $after);
        $this->assertArrayNotHasKey(IndexRebuild::META_REPLICAS, $builder->make()->mappings->meta());
    }

    /**
     * @test
     */
    public function language_keeps_the_serverless_flag(): void
    {
        $serverless = (new NewIndex($this->connection()))
            ->alias('articles')
            ->serverless(true)
            ->shards(4)
            ->replicas(3)
            ->make()
            ->settings
            ->toRaw();

        $afterLanguage = (new NewIndex($this->connection()))
            ->alias('articles')
            ->serverless(true)
            ->shards(4)
            ->replicas(3)
            ->language(new English)
            ->make()
            ->settings
            ->toRaw();

        $this->assertArrayNotHasKey('number_of_shards', $serverless);
        $this->assertArrayNotHasKey('number_of_replicas', $serverless);
        $this->assertArrayNotHasKey('number_of_shards', $afterLanguage);
        $this->assertArrayNotHasKey('number_of_replicas', $afterLanguage);
    }

    /**
     * @test
     */
    public function get_index_uses_the_physical_name_for_a_date_math_index(): void
    {
        $connection = new class implements ElasticsearchConnection
        {
            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $body = [
                    'logs-2026.11.23' => [
                        'aliases' => ['logs' => new stdClass],
                        'mappings' => ['properties' => new stdClass],
                        'settings' => ['index' => [
                            'number_of_shards' => '1',
                            'number_of_replicas' => '0',
                            'provided_name' => '<logs-{now/d}>',
                        ]],
                    ],
                ];

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) json_encode($body)));
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

        $index = (new Sigmie($connection))->index('logs');

        $this->assertSame('logs-2026.11.23', $index->name);
    }

    /**
     * @test
     */
    public function listed_index_dataset_size_is_the_dataset_column(): void
    {
        $listed = ListedIndex::fromRaw([
            'index' => 'movies',
            'health' => 'green',
            'status' => 'open',
            'uuid' => 'abc',
            'pri' => '1',
            'rep' => '0',
            'docs.count' => '2',
            'docs.deleted' => '0',
            'store.size' => '20kb',
            'pri.store.size' => '10kb',
            'dataset.size' => '12kb',
        ]);

        $this->assertSame('12kb', $listed->datasetSize);
        $this->assertSame('10kb', $listed->primaryStoreSize);
    }

    /**
     * @test
     */
    public function a_listed_index_without_a_dataset_column_uses_the_primary_store(): void
    {
        $listed = ListedIndex::fromRaw([
            'index' => 'movies',
            'health' => 'green',
            'status' => 'open',
            'uuid' => 'abc',
            'pri' => '1',
            'rep' => '0',
            'docs.count' => '2',
            'docs.deleted' => '0',
            'store.size' => '20kb',
            'pri.store.size' => '10kb',
            'creation.date.string' => '2024-01-15T12:30:45.000Z',
        ], ['movies']);

        $this->assertSame('movies', $listed->name);
        $this->assertSame(2, $listed->documentsCount);
        $this->assertSame('20kb', $listed->storeSize);
        $this->assertSame('10kb', $listed->primaryStoreSize);
        $this->assertSame('10kb', $listed->datasetSize);
        $this->assertSame(['movies'], $listed->aliases);
    }

    /**
     * @test
     */
    public function a_serverless_update_accepts_null_replicas(): void
    {
        $connection = new class implements ElasticsearchConnection
        {
            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                if ($request->getUri()->getPath() !== '/_reindex' || ! str_contains($request->getUri()->getQuery(), 'wait_for_completion=false')) {
                    throw new RuntimeException('Expected an async reindex request.');
                }

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"task":"node:1"}'));
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

        $replicas = (new UpdateIndex($connection))->alias('movies')->make()->settings->replicaShards();
        $task = new IndexUpdateTask($connection, 'movies_old', 'movies_new', 'movies', 'movies', $replicas);

        $this->assertInstanceOf(IndexUpdateTask::class, $task);
        $this->assertNull($task->pack()['requested_replicas']);
        $this->assertNull(IndexUpdateTask::unpack($connection, $task->pack())->requestedReplicas);
    }

    /**
     * @test
     */
    public function an_index_template_reads_its_patterns_from_raw(): void
    {
        $template = IndexTemplate::fromRaw('logs', [
            'index' => [
                'number_of_shards' => '1',
                'number_of_replicas' => '0',
            ],
            'mappings' => [
                'properties' => [
                    'message' => ['type' => 'text'],
                ],
            ],
            'index_patterns' => ['logs-*'],
        ]);

        $this->assertInstanceOf(IndexTemplate::class, $template);
        $this->assertSame('logs', $template->name);
        $this->assertSame(['logs-*'], $template->patterns);
    }

    /**
     * @test
     */
    public function finish_deletes_only_the_previous_index(): void
    {
        $connection = new class implements ElasticsearchConnection
        {
            /** @var list<string> */
            public array $aliasTargets = ['movies_old'];

            /** @var list<string> */
            public array $deletes = [];

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $method = $request->getMethod();
                $path = $request->getUri()->getPath();
                $body = json_decode((string) $request->getBody(), true) ?? [];

                if (str_ends_with($path, '/_count')) {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"count":3}'));
                }

                if ($method === 'HEAD' && str_starts_with($path, '/movies_old/_alias/')) {
                    return $request->response(new PsrResponse(404));
                }

                if ($method === 'HEAD' && str_contains($path, '/_alias/')) {
                    $status = $this->aliasTargets === [] ? 404 : 200;

                    return $request->response(new PsrResponse($status, ['Content-Type' => 'application/json'], ''));
                }

                if ($method === 'GET' && in_array($path, ['/_alias/movies', '/movies'], true)) {
                    $data = [];
                    foreach ($this->aliasTargets as $index) {
                        $data[$index] = ['aliases' => ['movies' => new stdClass]];
                        if ($path === '/movies') {
                            $data[$index] += ['settings' => ['index' => []], 'mappings' => []];
                        }
                    }

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) json_encode($data)));
                }

                if ($method === 'POST' && str_ends_with($path, '/_aliases')) {
                    foreach ($body['actions'] ?? [] as $action) {
                        if (isset($action['remove'])) {
                            $this->aliasTargets = array_values(array_filter(
                                $this->aliasTargets,
                                fn (string $index): bool => $index !== $action['remove']['index'],
                            ));
                        }

                        if (($action['add']['alias'] ?? null) === 'movies'
                            && ! in_array($action['add']['index'], $this->aliasTargets, true)) {
                            $this->aliasTargets[] = $action['add']['index'];
                        }
                    }

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($method === 'DELETE') {
                    $name = trim($path, '/');
                    $this->deletes[] = $name;
                    $this->aliasTargets = array_values(array_filter(
                        $this->aliasTargets,
                        fn (string $index): bool => $index !== $name,
                    ));

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
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

        $rebuild = new IndexRebuild($connection, 'movies', 'movies_new', 1, [], ['movies_old']);
        $rebuild->finish();
        $rebuild->finish();

        $this->assertSame(['movies_old'], $connection->deletes);
    }

    /**
     * @test
     */
    public function a_slower_rebuild_leaves_its_index_behind(): void
    {
        $connection = new class implements ElasticsearchConnection
        {
            /** @var list<string> */
            public array $aliasTargets = ['movies_fast'];

            /** @var list<string> */
            public array $deletes = [];

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $method = $request->getMethod();
                $path = $request->getUri()->getPath();
                $body = json_decode((string) $request->getBody(), true) ?? [];

                if (str_ends_with($path, '/_count')) {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"count":3}'));
                }

                if ($method === 'HEAD') {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], ''));
                }

                if ($method === 'GET' && in_array($path, ['/_alias/movies', '/movies'], true)) {
                    $data = [];
                    foreach ($this->aliasTargets as $index) {
                        $data[$index] = ['aliases' => ['movies' => new stdClass]];
                        if ($path === '/movies') {
                            $data[$index] += ['settings' => ['index' => []], 'mappings' => []];
                        }
                    }

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) json_encode($data)));
                }

                if ($method === 'POST' && $path === '/_aliases') {
                    foreach ($body['actions'] ?? [] as $action) {
                        if (isset($action['remove'])) {
                            $this->aliasTargets = array_values(array_filter(
                                $this->aliasTargets,
                                fn (string $index): bool => $index !== $action['remove']['index'],
                            ));
                        }

                        if (($action['add']['alias'] ?? null) === 'movies'
                            && ! in_array($action['add']['index'], $this->aliasTargets, true)) {
                            $this->aliasTargets[] = $action['add']['index'];
                        }
                    }

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($method === 'DELETE') {
                    $this->deletes[] = trim($path, '/');

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
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

        $slow = new IndexRebuild($connection, 'movies', 'movies_slow', 1);
        $returned = null;
        $thrown = null;

        try {
            $returned = $slow->finish();
        } catch (Throwable $throwable) {
            $thrown = $throwable;
        }

        $this->assertNull($returned);
        $this->assertInstanceOf(RebuildLost::class, $thrown);
        $this->assertSame([], $connection->deletes);
        $this->assertSame(['movies_fast'], $connection->aliasTargets);

        $connection->aliasTargets = ['movies_old'];
        $fast = new IndexRebuild($connection, 'movies', 'movies_fast', 1, [], ['movies_old']);
        $later = new IndexRebuild($connection, 'movies', 'movies_slow', 1, [], ['movies_old']);
        $fast->finish();
        $thrown = null;

        try {
            $later->finish();
        } catch (Throwable $throwable) {
            $thrown = $throwable;
        }

        $this->assertInstanceOf(RebuildLost::class, $thrown);
        $this->assertSame(['movies_old'], $connection->deletes);
        $this->assertSame(['movies_fast'], $connection->aliasTargets);
    }

    /**
     * @test
     */
    public function a_numeric_index_name_stays_a_string(): void
    {
        $connection = new class implements ElasticsearchConnection
        {
            /** @var list<string> */
            public array $aliasTargets = ['10'];

            /** @var list<string> */
            public array $deletes = [];

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $method = $request->getMethod();
                $path = $request->getUri()->getPath();
                $body = json_decode((string) $request->getBody(), true) ?? [];

                if (str_ends_with($path, '/_count')) {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"count":1}'));
                }

                if ($method === 'HEAD' && str_contains($path, '/_alias/')) {
                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], ''));
                }

                if ($method === 'GET' && in_array($path, ['/_alias/films', '/films'], true)) {
                    $data = [];
                    foreach ($this->aliasTargets as $index) {
                        $data[$index] = ['aliases' => ['films' => new stdClass]];
                        if ($path === '/films') {
                            $data[$index] += ['settings' => ['index' => []], 'mappings' => []];
                        }
                    }

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) json_encode($data)));
                }

                if ($method === 'POST' && str_ends_with($path, '/_aliases')) {
                    foreach ($body['actions'] ?? [] as $action) {
                        if (isset($action['remove'])) {
                            $this->aliasTargets = array_values(array_filter(
                                $this->aliasTargets,
                                fn (string $index): bool => $index !== $action['remove']['index'],
                            ));
                        }

                        if (($action['add']['alias'] ?? null) === 'films'
                            && ! in_array($action['add']['index'], $this->aliasTargets, true)) {
                            $this->aliasTargets[] = $action['add']['index'];
                        }
                    }

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                if ($method === 'DELETE') {
                    $this->deletes[] = trim($path, '/');

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
                }

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
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

        $rebuild = new IndexRebuild($connection, 'films', 'films_new', 1, [], ['10']);
        $index = $rebuild->finish();

        $this->assertInstanceOf(AliasedIndex::class, $index);
        $this->assertSame(['10'], $connection->deletes);
    }

    /**
     * @test
     */
    public function a_null_replica_count_is_omitted_from_the_settings(): void
    {
        $connection = new class implements ElasticsearchConnection
        {
            public array $bodies = [];

            public string $destination = 'movies_new';

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $raw = (string) $request->getBody();
                $body = $raw === '' ? null : json_decode($raw, true);
                if (is_array($body) && array_key_exists('refresh_interval', $body)) {
                    $this->bodies[] = $body;
                }

                $path = rawurldecode($request->getUri()->getPath());
                if ($request->getMethod() === 'PUT' && ! str_contains($path, '/_')) {
                    $this->destination = ltrim($path, '/');
                }

                if (str_ends_with($path, '/_block/write')) {
                    return $request->response(new PsrResponse(200, [], '{"shards_acknowledged":true}'));
                }

                $json = match ($path) {
                    '/_reindex' => str_contains($request->getUri()->getQuery(), 'wait_for_completion=false')
                        ? '{"task":"node:1"}'
                        : '{"took":1,"total":0,"created":0,"timed_out":false,"failures":[]}',
                    '/_tasks/node:1' => '{"completed":true,"response":{"took":1,"total":0,"created":0,"timed_out":false,"failures":[]}}',
                    '/_alias/movies' => '{"movies_old":{"aliases":{"movies":{}}}}',
                    '/movies' => '{"'.$this->destination.'":{"aliases":{"movies":{}},"mappings":{"properties":{}},"settings":{"index":{"number_of_shards":"1","number_of_replicas":"1"}}}}',
                    default => '{"acknowledged":true}',
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

        $index = new AliasedIndex('movies_old', 'movies');
        $index->setElasticsearchConnection($connection);
        $index->update(fn ($update) => $update->serverless(true));

        $this->assertSame(['refresh_interval' => '1s'], $connection->bodies[0]);

        $task = new IndexUpdateTask($connection, 'movies_old', $connection->destination, 'movies', 'movies', null);
        $task->finish();

        $this->assertSame(['refresh_interval' => '1s'], $connection->bodies[1]);
    }

    /**
     * @test
     */
    public function a_declared_refresh_interval_survives_the_update(): void
    {
        $connection = new class implements ElasticsearchConnection
        {
            /** @var list<array{path: string, body: array<string, mixed>}> */
            public array $writes = [];

            public ?string $created = null;

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $path = rawurldecode($request->getUri()->getPath());
                $method = $request->getMethod();
                $body = json_decode((string) $request->getBody(), true);

                if ($method === 'PUT' && is_array($body) && (array_key_exists('refresh_interval', $body) || isset($body['settings']['refresh_interval']))) {
                    $this->writes[] = ['path' => $path, 'body' => $body['settings'] ?? $body];
                }

                if ($method === 'PUT' && ! str_contains($path, '/_')) {
                    $this->created = trim($path, '/');
                }

                if ($path === '/movies') {
                    $name = $this->created ?? 'movies_new';

                    return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) json_encode([
                        $name => [
                            'aliases' => ['movies' => new stdClass],
                            'mappings' => ['properties' => new stdClass],
                            'settings' => ['index' => ['number_of_shards' => '1', 'number_of_replicas' => '3']],
                        ],
                    ])));
                }

                if (str_ends_with($path, '/_block/write')) {
                    return $request->response(new PsrResponse(200, [], '{"shards_acknowledged":true}'));
                }

                $json = match ($path) {
                    '/_reindex' => str_contains($request->getUri()->getQuery(), 'wait_for_completion=false')
                        ? '{"task":"node:1"}'
                        : '{"took":1,"total":0,"created":0,"timed_out":false,"failures":[]}',
                    '/_tasks/node:1' => '{"completed":true,"response":{"took":1,"total":0,"created":0,"timed_out":false,"failures":[]}}',
                    '/_alias/movies' => '{"movies_old":{"aliases":{"movies":{}}}}',
                    default => '{"acknowledged":true}',
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

        $index = new AliasedIndex('movies_old', 'movies');
        $index->setElasticsearchConnection($connection);

        $props = new NewProperties;
        $props->title('title');

        $declare = function (UpdateIndex $update) use ($props): UpdateIndex {
            $update->properties($props);
            $update->lowercase('lower');
            $update->replicas(3);
            $update->config('index.refresh_interval', '30s');

            return $update;
        };

        $index->update($declare);

        $created = array_values(array_filter(
            $connection->writes,
            fn (array $write): bool => ! str_contains($write['path'], '/_settings'),
        ));
        $restored = array_values(array_filter(
            $connection->writes,
            fn (array $write): bool => str_contains($write['path'], '/_settings'),
        ));

        $this->assertSame('30s', $created[0]['body']['refresh_interval']);
        $this->assertArrayNotHasKey('index.refresh_interval', $created[0]['body']);
        $this->assertSame(0, $created[0]['body']['number_of_replicas']);
        $this->assertSame('30s', $restored[0]['body']['refresh_interval']);
        $this->assertSame(3, $restored[0]['body']['number_of_replicas']);

        $packed = $index->asyncUpdate($declare)->pack();
        $this->assertSame('30s', $packed['requested_refresh']);
        IndexUpdateTask::unpack($connection, $packed)->finish();

        $restored = array_values(array_filter(
            $connection->writes,
            fn (array $write): bool => str_contains($write['path'], '/_settings'),
        ));
        $last = $restored[array_key_last($restored)]['body'];

        $this->assertSame('30s', $last['refresh_interval']);
        $this->assertSame(3, $last['number_of_replicas']);

        $before = count($connection->writes);
        $index->update(function (UpdateIndex $update): UpdateIndex {
            $update->replicas(3);
            $update->config('refresh_interval', '-1');

            return $update;
        });
        $explicit = array_values(array_filter(
            array_slice($connection->writes, $before),
            fn (array $write): bool => str_contains($write['path'], '/_settings'),
        ));

        $this->assertSame('-1', $explicit[0]['body']['refresh_interval']);
        $this->assertSame(3, $explicit[0]['body']['number_of_replicas']);

        $before = count($connection->writes);
        $index->update(fn (UpdateIndex $update): UpdateIndex => $update->replicas(3));
        $omitted = array_values(array_filter(
            array_slice($connection->writes, $before),
            fn (array $write): bool => str_contains($write['path'], '/_settings'),
        ));

        $this->assertSame('1s', $omitted[0]['body']['refresh_interval']);
        $this->assertSame(3, $omitted[0]['body']['number_of_replicas']);
    }

    /**
     * @test
     */
    public function a_physical_index_named_01_is_not_the_alias_1(): void
    {
        $connection = new class implements ElasticsearchConnection
        {
            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $path = $request->getUri()->getPath();
                $json = $path === '/01'
                    ? '{"01":{"aliases":{"1":{}},"mappings":{"properties":{}},"settings":{"index":{"number_of_shards":"1","number_of_replicas":"1"}}}}'
                    : '{"1":{"aliases":{"1":{}},"mappings":{"properties":{}},"settings":{"index":{"number_of_shards":"1","number_of_replicas":"1"}}}}';

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

        $sigmie = new Sigmie($connection);

        $this->assertInstanceOf(Index::class, $sigmie->index('01'));
        $this->assertNotInstanceOf(AliasedIndex::class, $sigmie->index('01'));
        $this->assertInstanceOf(AliasedIndex::class, $sigmie->index('1'));
    }

    /**
     * @test
     */
    public function an_aliased_index_from_raw_keeps_the_alias(): void
    {
        $index = AliasedIndex::fromRaw('movies_20260101', [
            'index' => [
                'number_of_shards' => '1',
                'number_of_replicas' => '0',
            ],
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text'],
                ],
            ],
            'aliases' => [
                'movies' => [],
            ],
        ]);

        $alias = (new ReflectionClass($index))->getProperty('alias');

        $this->assertInstanceOf(AliasedIndex::class, $index);
        $this->assertSame('movies_20260101', $index->name);
        $this->assertSame('movies', $alias->getValue($index));
    }

    /**
     * @test
     */
    public function a_mapping_field_named_zero_is_read_from_the_index(): void
    {
        $index = Index::fromRaw('movies_20260101', [
            'index' => [
                'number_of_shards' => '1',
                'number_of_replicas' => '0',
            ],
            'mappings' => [
                'properties' => [
                    '0' => ['type' => 'keyword'],
                    '10' => ['type' => 'text'],
                    'title' => ['type' => 'text'],
                ],
            ],
        ]);

        $this->assertSame('0', $index->mappings->properties()->get('0')->name());
        $this->assertSame('keyword', $index->mappings->properties()->get('0')->typeName());
        $this->assertSame('10', $index->mappings->properties()->get('10')->name());
        $this->assertSame('text', $index->mappings->properties()->get('10')->typeName());
        $this->assertSame('title', $index->mappings->properties()->get('title')->name());
        $this->assertSame(1, $index->settings->primaryShards());
        $this->assertSame(0, $index->settings->replicaShards());
    }

    /**
     * @test
     */
    public function a_numeric_name_stays_on_the_mapping_and_the_settings(): void
    {
        $driver = new Elasticsearch;
        $props = new NewProperties;
        $props->text('1');

        $mapping = (new Mappings(properties: $props->get(new Analysis)))->toRaw($driver);
        $json = json_encode($mapping['properties']);

        $this->assertStringContainsString('"1":', $json);
        $this->assertStringNotContainsString('"0":', $json);

        $made = (new NewIndex($this->connection()))
            ->alias('movies')
            ->meta(['1' => 'kept', 'label' => 'a'])
            ->config('1', 'kept')
            ->make();

        $this->assertSame('kept', $made->mappings->meta()[1]);
        $this->assertArrayNotHasKey(0, $made->mappings->meta());
        $this->assertSame('kept', $made->settings->toRaw()[1]);
        $this->assertArrayNotHasKey(0, $made->settings->toRaw());
        $this->assertSame('kept', (new Settings(configs: ['1' => 'kept']))->toRaw()[1]);
    }

    private function connection(): ElasticsearchConnection
    {
        return new class implements ElasticsearchConnection
        {
            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"acknowledged":true}'));
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
    }

    public function test_literal_dotted_alias_in_index_metadata(): void
    {
        $index = new AliasedIndex('products', 'store.eu', raw: ['aliases' => ['store.eu' => ['is_write_index' => true]]]);
        $this->assertTrue($index->get('aliases.store.eu.is_write_index'));
    }

    public function test_physical_index_names_follow_utc_through_the_fall_dst_transition(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        $index = (new NewIndex($this->connection()))->alias('movies');

        try {
            Carbon::setTestNow(Carbon::parse('2024-11-03T01:59:59-04:00'));
            $earlier = $index->make()->name;
            Carbon::setTestNow(Carbon::parse('2024-11-03T01:00:00-05:00'));
            $later = $index->make()->name;
        } finally {
            Carbon::setTestNow();
            date_default_timezone_set($zone);
        }

        $this->assertSame('movies_20241103055959000000', $earlier);
        $this->assertSame('movies_20241103060000000000', $later);
    }

    public function test_pending_rebuild_uses_creation_time_when_legacy_names_sort_in_reverse(): void
    {
        $earlier = 'movies_20241103015959000000';
        $later = 'movies_20241103010000000000';
        $index = static fn (string $name, string $creation): array => [
            'aliases' => [IndexRebuild::pendingAlias($name) => []],
            'mappings' => ['_meta' => [IndexRebuild::META_KEY => 'movies']],
            'settings' => ['index' => ['creation_date' => $creation]],
        ];

        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(fn (ElasticsearchRequest $request): ElasticsearchResponse => $request->response(
            new PsrResponse(200, [], json_encode([
                $earlier => $index($earlier, '1730613599000'),
                $later => $index($later, '1730613600000'),
            ], JSON_THROW_ON_ERROR)),
        ));

        $this->assertSame($later, (new Sigmie($connection))->rebuilding('movies')?->name);
    }

    public function test_starting_an_update_requires_a_reindex_task_id(): void
    {
        $connection = $this->indexResponses(['POST /_reindex' => new PsrResponse(200, [], '{}')]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reindex did not return a task ID.');

        new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 0);
    }

    public function test_unpacking_an_update_requires_its_task_id(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->expects($this->never())->method('__invoke');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Packed index update is missing its task ID.');

        IndexUpdateTask::unpack($connection, [
            'source' => 'source',
            'dest' => 'dest',
            'old_alias' => 'movies',
            'new_alias' => 'movies',
            'requested_replicas' => 0,
        ]);
    }

    public function test_task_status_requires_a_boolean_completion_flag(): void
    {
        $connection = $this->indexResponses(['GET /_tasks/node%3A1' => new PsrResponse(200, [], '{"completed":"true"}')]);
        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 0, task: 'node:1');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reindex task status did not report whether it completed.');

        $task->isCompleted();
    }

    public function test_unsuccessful_completed_task_restores_source_writes(): void
    {
        $connection = $this->indexResponses([
            'GET /_tasks/node%3A1' => new PsrResponse(200, [], '{"completed":true,"response":{"failures":[],"timed_out":false,"updated":1}}'),
            'HEAD /source' => new PsrResponse(200),
            'PUT /source/_settings' => new PsrResponse(200, [], '{"acknowledged":true}'),
        ], ['PUT /source/_settings' => ['index' => ['blocks.write' => false]]], requestCount: 3);
        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 0, task: 'node:1');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reindex did not complete successfully.');

        $task->isCompleted();
    }

    public function test_finish_rejects_an_update_that_is_still_running(): void
    {
        $connection = $this->indexResponses([
            'HEAD /_alias/movies' => new PsrResponse(404),
            'GET /_tasks/node%3A1' => new PsrResponse(200, [], '{"completed":false}'),
        ]);
        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 0, task: 'node:1');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Index update is not completed.');

        $task->finish();
    }

    public function test_wait_and_finish_propagates_a_task_permission_error(): void
    {
        $error = new ElasticsearchException(['type' => 'security_exception'], 403);
        $connection = $this->indexResponses(['GET /_tasks/node%3A1' => $error]);
        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 0, task: 'node:1');
        $this->expectExceptionObject($error);

        $task->waitAndFinish();
    }

    public function test_null_shard_settings_keep_the_configured_counts(): void
    {
        $settings = (new NewIndex($this->connection()))
            ->alias('movies')
            ->shards(2)
            ->replicas(1)
            ->config('index.number_of_shards', null)
            ->config('index.number_of_replicas', null)
            ->make()
            ->settings;

        $this->assertSame(2, $settings->primaryShards());
        $this->assertSame(1, $settings->replicaShards());
    }

    public function test_shard_count_rejects_fractional_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The index setting 'index.number_of_shards' requires an integer.");

        (new NewIndex($this->connection()))->config('index.number_of_shards', '2.5');
    }

    public function test_language_selection_uses_its_configured_tokenizer(): void
    {
        $connection = $this->connection();
        $english = (new English)->builder($connection)->dontTokenize('whole_title');
        $language = $this->createStub(Language::class);
        $language->method('builder')->willReturn($english);

        $analysis = (new NewIndex($connection))
            ->alias('movies')
            ->language($language)
            ->make()
            ->settings
            ->analysis()
            ->toRaw();

        $this->assertSame('whole_title', $analysis['analyzer']['default']['tokenizer']);
        $this->assertSame(['type' => 'keyword'], $analysis['tokenizer']['whole_title']);
    }

    public function test_configuring_a_builtin_default_analyzer_pipeline_fails(): void
    {
        $builder = new NewIndex($this->connection());
        $builder->analysis()->addAnalyzer(new BuiltInAnalyzer('default', ['type' => 'standard']));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The default analyzer must be custom to configure its pipeline.');

        $builder->dontTokenize();
    }

    public function test_create_if_not_exists_propagates_permission_errors(): void
    {
        $error = new ElasticsearchException(['type' => 'security_exception'], 403);
        $connection = $this->indexResponses([
            'HEAD /_alias/movies' => new PsrResponse(404),
            'HEAD /movies' => $error,
        ]);
        $this->expectExceptionObject($error);

        (new NewIndex($connection))->alias('movies')->createIfNotExists();
    }

    public function test_create_if_not_exists_reports_a_conflicting_physical_index(): void
    {
        $connection = $this->indexResponses([
            'HEAD /_alias/movies' => new PsrResponse(404),
            'HEAD /movies' => new PsrResponse(200),
            'GET /movies' => new PsrResponse(200, [], '{"movies":{"aliases":{},"settings":{},"mappings":{}}}'),
        ]);
        $this->expectException(AliasAlreadyExists::class);
        $this->expectExceptionMessage("An index with alias 'movies' already exists.");

        (new NewIndex($connection))->alias('movies')->createIfNotExists();
    }

    public function test_search_synonyms_reject_a_builtin_default_analyzer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Search synonyms require a custom default analyzer.');

        (new NewIndex($this->connection()))
            ->alias('movies')
            ->config('analysis.analyzer.default', ['type' => 'keyword'])
            ->searchSynonyms(['film, movie'])
            ->make();
    }

    public function test_rebuild_finish_never_deletes_the_destination_from_a_retirement_list(): void
    {
        $connection = $this->indexResponses([
            'HEAD /_alias/movies' => new PsrResponse(200),
            'GET /_alias/movies' => new PsrResponse(200, [], '{"dest":{"aliases":{"movies":{}}}}'),
            'GET /movies' => new PsrResponse(200, [], '{"dest":{"aliases":{"movies":{}},"settings":{},"mappings":{}}}'),
        ]);
        $rebuild = new IndexRebuild($connection, 'movies', 'dest', 0, startedOn: ['dest']);

        $this->assertSame('dest', $rebuild->finish()->name);
    }

    public function test_rebuild_does_not_delete_a_source_if_retirement_cannot_be_confirmed(): void
    {
        $retired = '.sigmie-retired-'.hash('sha256', 'dest');
        $connection = $this->indexResponses([
            'HEAD /_alias/movies' => new PsrResponse(200),
            'GET /_alias/movies' => new PsrResponse(200, [], '{"dest":{"aliases":{"movies":{}}}}'),
            'HEAD /source/_alias/'.$retired => new PsrResponse(204),
        ]);
        $rebuild = new IndexRebuild($connection, 'movies', 'dest', 0, startedOn: ['source']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Could not confirm retirement of 'source'.");

        $rebuild->finish();
    }

    public function test_failed_update_propagates_an_error_releasing_its_destination_lock(): void
    {
        $owner = '.sigmie-update-source-'.hash('sha256', 'dest');
        $lock = '.sigmie-update-lock-'.hash('sha256', 'source');
        $error = new ElasticsearchException(['type' => 'security_exception'], 403);
        $connection = $this->indexResponses([
            'GET /_tasks/node%3A1' => new PsrResponse(200, [], '{"completed":true,"response":{"failures":[],"timed_out":false,"updated":1}}'),
            'PUT /'.$owner.'/_settings' => new ElasticsearchException(['type' => 'index_not_found_exception'], 404),
            'DELETE /dest/_alias/'.$lock => $error,
        ]);
        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 0, task: 'node:1', ownsUpdateLock: true);
        $this->expectExceptionObject($error);

        $task->isCompleted();
    }

    public function test_update_does_not_promote_an_index_when_settings_are_unacknowledged(): void
    {
        $connection = $this->indexResponses([
            'HEAD /_alias/movies' => new PsrResponse(404),
            'GET /_tasks/node%3A1' => new PsrResponse(200, [], '{"completed":true,"response":{"failures":[],"timed_out":false}}'),
            'PUT /dest/_settings' => new PsrResponse(200, [], '{}'),
        ]);
        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'movies', 0, task: 'node:1');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Destination settings for 'dest' were not acknowledged.");

        $task->finish();
    }

    public function test_rebuild_rejects_an_unexpected_alias_lookup_status(): void
    {
        $connection = $this->indexResponses(['HEAD /_alias/movies' => new PsrResponse(204)]);
        $rebuild = new IndexRebuild($connection, 'movies', 'dest', 0);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Could not resolve alias 'movies'.");

        $rebuild->finish();
    }

    public function test_finishing_an_update_refuses_to_replace_an_occupied_new_alias(): void
    {
        $connection = $this->indexResponses([
            'HEAD /_alias/new-movies' => new PsrResponse(200),
            'GET /_alias/new-movies' => new PsrResponse(200, [], '{"occupied":{"aliases":{"new-movies":{}}}}'),
            'GET /_tasks/node%3A1' => new PsrResponse(200, [], '{"completed":true,"response":{"failures":[],"timed_out":false}}'),
            'PUT /dest/_settings' => new PsrResponse(200, [], '{"acknowledged":true}'),
        ]);
        $task = new IndexUpdateTask($connection, 'source', 'dest', 'movies', 'new-movies', 0, task: 'node:1');
        $this->expectException(AliasAlreadyExists::class);
        $this->expectExceptionMessage("An index with alias 'new-movies' already exists.");

        $task->finish();
    }

    public function test_rebuild_rejects_source_indices_with_different_alias_filters(): void
    {
        $connection = $this->indexResponses([
            'HEAD /_alias/movies' => new PsrResponse(200),
            'GET /_alias/movies' => new PsrResponse(200, [], '{"one":{"aliases":{"movies":{"filter":{"term":{"year":1999}}}}},"two":{"aliases":{"movies":{"filter":{"term":{"year":2003}}}}}}'),
            'GET /one,two' => new PsrResponse(200, [], '{"one":{"aliases":{"movies":{}}},"two":{"aliases":{"movies":{}}}}'),
            'POST /dest/_refresh' => new PsrResponse(200, [], '{}'),
            'GET /dest/_count' => new PsrResponse(200, [], '{"count":2}'),
            'PUT /dest/_settings' => new PsrResponse(200, [], '{"acknowledged":true}'),
        ]);
        $rebuild = new IndexRebuild($connection, 'movies', 'dest', 0, startedOn: ['one', 'two'], refresh: '1s');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Alias 'movies' has different settings on its source indices.");

        $rebuild->finish();
    }

    public function test_abort_keeps_the_index_if_its_alias_update_is_unacknowledged(): void
    {
        $connection = $this->indexResponses(['POST /_aliases' => new PsrResponse(200, [], '{"acknowledged":false}')]);
        $rebuild = new IndexRebuild($connection, 'movies', 'dest', 0);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Alias update for 'movies' was not acknowledged.");

        $rebuild->abort();
    }

    public function test_aliased_index_requires_an_alias_in_the_payload(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An aliased index payload needs an alias name.');

        AliasedIndex::fromRaw('movies', ['aliases' => [], 'settings' => [], 'mappings' => []]);
    }

    public function test_disabling_writes_requires_acknowledgement_by_all_shards(): void
    {
        $connection = $this->indexResponses([
            'PUT /source/_block/write' => new PsrResponse(200, [], '{"acknowledged":true,"shards_acknowledged":false,"indices":[{"name":"source","blocked":true}]}'),
        ]);
        $index = new AliasedIndex('source', 'movies');
        $index->setElasticsearchConnection($connection);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Write block for 'source' was not acknowledged by all shards.");

        $index->disableWrite();
    }

    public function test_abort_succeeds_when_the_rebuild_index_is_already_missing(): void
    {
        $marker = '.sigmie-rebuild-aborted-'.hash('sha256', 'dest');
        $connection = $this->indexResponses([
            'POST /_aliases' => new ElasticsearchException(['type' => 'index_not_found_exception'], 404),
        ], ['POST /_aliases' => ['actions' => [
            ['remove' => ['index' => 'dest', 'alias' => IndexRebuild::pendingAlias('dest'), 'must_exist' => true]],
            ['add' => ['index' => 'dest', 'alias' => $marker, 'is_hidden' => true]],
        ]]], requestCount: 1);
        $rebuild = new IndexRebuild($connection, 'movies', 'dest', 0);

        $rebuild->abort();
    }

    public function test_abort_reports_an_unexpected_marker_lookup_status(): void
    {
        $marker = '.sigmie-rebuild-aborted-'.hash('sha256', 'dest');
        $connection = $this->indexResponses([
            'POST /_aliases' => new ElasticsearchException(['type' => 'aliases_not_found_exception'], 404),
            'HEAD /dest/_alias/'.$marker => new PsrResponse(204),
        ]);
        $rebuild = new IndexRebuild($connection, 'movies', 'dest', 0);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Could not resolve abort status for 'dest'.");
        $this->expectExceptionCode(404);

        $rebuild->abort();
    }

    public function test_index_settings_reject_scalar_analysis(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The analysis setting requires an array of named components.');

        (new Settings)->config('index.analysis', 'standard')->toRaw();
    }

    public function test_analysis_rejects_a_conflicting_token_filter_definition(): void
    {
        $stored = Analysis::fromRaw(['filter' => ['common_words' => ['type' => 'stop', 'stopwords' => ['the']]]]);
        $incoming = Analysis::fromRaw(['filter' => ['common_words' => ['type' => 'stop', 'stopwords' => ['a']]]]);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Token filter `common_words` already exists.');

        $incoming->registerFieldComponents($stored);
    }

    public function test_analysis_rejects_a_conflicting_char_filter_definition(): void
    {
        $stored = Analysis::fromRaw(['char_filter' => ['symbols' => ['type' => 'mapping', 'mappings' => ['& => and']]]]);
        $incoming = Analysis::fromRaw(['char_filter' => ['symbols' => ['type' => 'mapping', 'mappings' => ['& => plus']]]]);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Char filter name `symbols` already exists.');

        $incoming->registerFieldComponents($stored);
    }

    public function test_mapping_ignores_combo_metadata_for_a_missing_parent(): void
    {
        $mapping = Mappings::create([
            'properties' => ['title' => ['type' => 'keyword']],
            '_meta' => [
                '_sigmie_fields' => [
                    'version' => 1,
                    'fields' => [['path' => 'removed.label', 'combo' => ['name' => 'label', 'sources' => ['title']], 'vectors' => []]],
                ],
            ],
        ], []);

        $this->assertSame(['title'], $mapping->fieldNames());
        $this->assertArrayNotHasKey('_sigmie_fields', (array) $mapping->toRaw(new Elasticsearch)['_meta']);
    }

    private function indexResponses(array $responses, array $expectedBodies = [], ?int $requestCount = null): ElasticsearchConnection
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('isServerless')->willReturn(false);
        $calls = $requestCount === null ? $this->atLeastOnce() : $this->exactly($requestCount);
        $connection->expects($calls)->method('__invoke')->willReturnCallback(
            function (ElasticsearchRequest $request) use ($responses, $expectedBodies): ElasticsearchResponse {
                $key = $request->getMethod().' '.$request->getUri()->getPath();
                $this->assertArrayHasKey($key, $responses);
                if (isset($expectedBodies[$key])) {
                    $this->assertSame($expectedBodies[$key], json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR));
                }

                $response = $responses[$key];
                if ($response instanceof ElasticsearchException) {
                    throw $response;
                }

                return $request->response($response);
            },
        );

        return $connection;
    }
}
