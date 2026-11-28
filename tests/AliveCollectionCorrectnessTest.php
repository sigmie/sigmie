<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Contracts\CollectionHook;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Mappings\Types\FlatObject;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\FilterParser;
use Sigmie\Semantic\DocumentProcessor;
use Sigmie\Testing\FakeEmbeddingsApi;
use stdClass;

class AliveCollectionCorrectnessTest extends TestCase
{
    public function test_read_keeps_nested_json_objects_distinct_from_lists(): void
    {
        $source = '{"metadata":{"empty":{},"numeric":{"0":"zero"},"list":[]}}';
        [$collection] = $this->movies('{"docs":[{"_id":"1","found":true,"_source":'.$source.'}]}');

        $doc = $collection->get('1');

        $this->assertSame($source, json_encode($doc->_source));
        $this->assertSame('zero', $doc['metadata.numeric.0']);
    }

    public function test_embedding_boost_reads_an_object_in_a_record_list(): void
    {
        $props = new NewProperties;
        $props->nested('items', fn (NewProperties $item): Number => $item->integer('weight'));
        $props->text('title')->semantic(api: 'fake', dimensions: 8, accuracy: 1)
            ->normalizeVector(false)->boostedBy('items.weight');
        $api = new FakeEmbeddingsApi($this->createStub(EmbeddingsApi::class), 10);
        $processor = (new DocumentProcessor($props->get()))->apis(['fake' => $api]);
        $doc = new Document(['title' => 'Ada', 'items' => [(object) ['weight' => 2]]]);

        $processor->populateEmbeddings($doc);

        $this->assertSame(
            array_map(fn (float $component): float => 2 * $component, $api->embed('Ada', 8)),
            array_values($doc['_embeddings']['title'])[0],
        );
    }

    public function test_combo_is_written_inside_object_records(): void
    {
        $props = new NewProperties;
        $props->nested('records', function (NewProperties $record): void {
            $record->keyword('x');
            $record->combo('label', ['x']);
        });
        $doc = new Document(['records' => [(object) ['x' => 'Ada']]]);
        (new DocumentProcessor($props->get()))->populateComboFields($doc);

        $this->assertSame('Ada', $doc->_source['records'][0]->label ?? null);
    }

    public function test_combo_reads_all_source_representations(): void
    {
        $props = new NewProperties;
        $props->object('info', fn (NewProperties $info): Keyword => $info->keyword('x'));
        $props->combo('summary', ['info.x']);

        $doc = new Document(['info.x' => 'Ada', 'info' => (object) ['x' => 'Grace']]);
        (new DocumentProcessor($props->get()))->populateComboFields($doc);

        $this->assertSame('Ada Grace', $doc['summary']);
    }

    public function test_combo_is_written_beside_a_dotted_sibling(): void
    {
        $props = new NewProperties;
        $props->object('info', function (NewProperties $info): void {
            $info->keyword('x');
            $info->combo('label', ['x']);
        });
        $doc = new Document(['info.x' => 'Ada']);
        (new DocumentProcessor($props->get()))->populateComboFields($doc);

        $this->assertSame(['info.x' => 'Ada', 'info.label' => 'Ada'], $doc->_source);
    }

    public function test_to_array_retains_documents_sharing_an_id(): void
    {
        [$collection] = $this->movies('{"hits":{"hits":[{"_index":"first","_id":"same"},{"_index":"second","_id":"same"},{"_index":"second","_id":"0"}]}}');

        $this->assertSame(['first', 'second', 'second'], array_column($collection->toArray(), '_index'));
    }

    public function test_sampling_combines_collection_filters(): void
    {
        [$collection, $captured] = $this->movies('{"hits":{"hits":[]}}');
        $props = new NewProperties;
        $props->keyword('genre');
        $props->keyword('language');

        $parser = new FilterParser($props);
        $genre = $parser->parse("genre:'drama'");
        $language = $parser->parse("language:'French'");

        $collection->filterQuery($genre)->filterQuery($language)->random(2);

        $query = json_decode($captured->bodies[0], true)['query']['function_score']['query'];
        $this->assertEquals([$genre->toRaw(), $language->toRaw()], $query['bool']['filter'] ?? []);
    }

    public function test_contract_collection_accepts_named_documents(): void
    {
        [$collection] = $this->movies('{}');
        $this->assertSame($collection, $collection->merge(documents: []));
    }

    public function test_contract_array_access_accepts_named_offsets_and_values(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"42","_index":"movies","status":201}}]}');
        $document = new Document(['title' => 'Solaris']);
        $collection->offsetSet(offset: '42', value: $document);
        $this->assertSame('42', $document->_id);
        $collection->offsetUnset(offset: '42');
        $this->assertCount(2, $captured->bodies);
    }

    public function test_a_document_without_an_assigned_index_round_trips(): void
    {
        foreach ([null, '0'] as $id) {
            $raw = (new Document(['price' => 0, 'active' => false], $id))->toArray();
            $copy = Document::fromRaw($raw);

            $this->assertSame($raw, $copy->toArray());
            $this->assertFalse(isset($copy->_index));
        }

        $copy = Document::fromRaw(['_source' => [], '_id' => '0', '_index' => '0']);
        $this->assertSame('0', $copy->_index);
    }

    public function test_take_from_the_end_uses_one_snapshot(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $closed = [];
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use (&$closed): ElasticsearchResponse {
            $body = json_decode((string) $request->getBody(), true);
            $response = match ($request->getUri()->getPath()) {
                '/movies/_count' => ['count' => 5],
                '/movies/_search' => ['hits' => ['hits' => [
                    ['_id' => 'four', '_index' => 'movies'],
                ]]],
                '/movies/_pit' => ['id' => 'snapshot'],
                '/_search' => ['pit_id' => 'latest', 'hits' => ['hits' => match ([$body['sort'], $body['search_after'] ?? null]) {
                    [[['_shard_doc' => 'desc']], null] => [['_id' => 'four', '_index' => 'movies', 'sort' => [4]]],
                    [[['_shard_doc' => 'desc']], [4]] => [['_id' => 'three', '_index' => 'movies', 'sort' => [3]]],
                    default => [],
                }]],
                '/_pit' => ['succeeded' => (bool) ($closed[] = $body['id'])],
            };

            return $request->response(new Response(200, [], json_encode($response)));
        });
        $collection = (new AliveCollection('movies', $connection))->chunk(1);

        $this->assertSame(['three', 'four'], array_column($collection->take(-2), '_id'));
        $this->assertSame(['latest'], $closed);
    }

    public function test_random_zero_returns_without_searching(): void
    {
        [$collection, $captured] = $this->movies('{"hits":{"hits":[]}}');

        $this->assertSame([], $collection->random(0)->toArray());
        $this->assertSame([], $captured->bodies);
    }

    public function test_an_empty_merge_does_not_run_batch_hooks(): void
    {
        [$collection, $captured] = $this->movies('{"items":[]}');
        $hook = $this->createMock(CollectionHook::class);
        $hook->method('shouldRun')->willReturn(true);
        $hook->expects($this->never())->method('beforeBatch');
        $hook->expects($this->never())->method('processBatch');
        $hook->expects($this->never())->method('afterBatch');

        $this->assertSame($collection, $collection->hooks([$hook])->merge([]));
        $this->assertSame([], $captured->bodies);
    }

    /**
     * @test
     *
     * @dataProvider partialShardCounts
     */
    public function count_rejects_a_partial_shard_response(int $failed): void
    {
        [$collection] = $this->movies(json_encode([
            'count' => 3,
            '_shards' => ['total' => 2, 'successful' => 1, 'failed' => $failed],
        ]));

        $this->expectException(ElasticsearchException::class);
        $collection->count();
    }

    public static function partialShardCounts(): array
    {
        return ['failed shard' => [1], 'unassigned shard' => [0]];
    }

    /**
     * @test
     */
    public function dotted_read_is_set(): void
    {
        $doc = new Document([
            'title' => 'Inception',
            'director' => [
                'name' => 'Christopher Nolan',
                'born' => 0,
            ],
        ]);

        $this->assertSame('Christopher Nolan', $doc['director.name']);
        $this->assertSame('Christopher Nolan', $doc['director.name'] ?? 'MISSING');
        $this->assertTrue(isset($doc['director.name']));
        $this->assertFalse(empty($doc['director.name']));
        $this->assertTrue($doc->offsetExists('director.name'));
        $this->assertSame(0, $doc['director.born'] ?? -1);
        $this->assertTrue(isset($doc['director.born']));
        $this->assertSame('MISSING', $doc['director.missing'] ?? 'MISSING');
        $this->assertFalse(isset($doc['director.missing']));
    }

    /**
     * @test
     */
    public function hit_from_raw_keeps_score(): void
    {
        $hit = Hit::fromRaw([
            '_index' => 'movies',
            '_id' => 'doc-1',
            '_score' => 1.0,
            '_source' => ['title' => 'A'],
        ]);

        $this->assertInstanceOf(Hit::class, $hit);
        $this->assertSame(1.0, $hit->_score);
        $this->assertSame('doc-1', $hit->_id);
    }

    /**
     * @test
     */
    public function null_index_hit_does_not_throw(): void
    {
        $hit = new Hit(['title' => 'A'], 'doc-1', 1.0);

        $this->assertSame('doc-1', $hit->_id);
        $this->assertSame(1.0, $hit->_score);
        $this->assertSame('movies', (new Hit(['title' => 'A'], 'doc-1', 1.0, 'movies'))->_index);
    }

    /**
     * @test
     */
    public function take_zero_returns_no_documents(): void
    {
        [$collection] = $this->movies('{"count":10}');

        $this->assertSame([], $collection->take(0));
    }

    /**
     * @test
     */
    public function replace_indexes_id_zero_in_one_operation(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"index":{"_id":"0","_index":"movies-01","status":200}}]}');

        $updated = $collection->replace(new Document(['title' => 'Zero'], '0'));

        $this->assertSame('0', $updated->_id);
        $this->assertSame([
            ['index' => ['_id' => '0']],
            ['title' => 'Zero'],
        ], $this->ndjson($captured->bodies[0]));
        $this->assertSame('movies-01', $updated->_index);
    }

    /**
     * @test
     */
    public function delete_documents_is_false_when_items_fail(): void
    {
        $failed = json_encode([
            'errors' => true,
            'items' => [
                ['delete' => ['status' => 404, 'result' => 'not_found']],
                ['delete' => ['status' => 500]],
            ],
        ], JSON_THROW_ON_ERROR);

        [$collection] = $this->movies($failed);

        $this->assertFalse($collection->deleteIds(['missing', 'broken']));

        $deleted = json_encode([
            'errors' => false,
            'items' => [
                ['delete' => ['status' => 200, 'result' => 'deleted']],
            ],
        ], JSON_THROW_ON_ERROR);

        [$ok] = $this->movies($deleted);

        $this->assertTrue($ok->deleteIds(['matrix_1']));
    }

    public function test_bulk_delete_surfaces_an_item_failure_after_a_missing_document(): void
    {
        [$collection] = $this->movies(json_encode([
            'errors' => true,
            'items' => [
                ['delete' => ['status' => 404, 'result' => 'not_found']],
                ['delete' => ['status' => 429, 'error' => ['type' => 'es_rejected_execution_exception', 'reason' => 'write queue is full']]],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(429);
        $this->expectExceptionMessage('write queue is full');
        $collection->remove(['missing', 'rejected']);
    }

    public function test_bulk_delete_returns_false_for_a_missing_document(): void
    {
        [$collection] = $this->movies('{"errors":false,"items":[{"delete":{"status":404,"result":"not_found"}}]}');

        $this->assertFalse($collection->remove(['missing']));
    }

    public function test_merge_exposes_every_failed_item_with_its_status_and_id(): void
    {
        [$collection] = $this->movies(json_encode([
            'errors' => true,
            'items' => [
                ['update' => ['_id' => 'bad-mapping', 'status' => 400, 'error' => ['type' => 'strict_dynamic_mapping_exception']]],
                ['update' => ['_id' => 'conflict', 'status' => 409, 'error' => ['type' => 'version_conflict_engine_exception']]],
                ['update' => ['_id' => 'overloaded', 'status' => 429, 'error' => ['type' => 'es_rejected_execution_exception']]],
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            $collection->merge([new Document([], 'bad-mapping'), new Document([], 'conflict'), new Document([], 'overloaded')]);
            $this->fail('All three writes failed.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(400, $elasticsearchException->getCode());
            $this->assertSame([400, 409, 429], array_column($elasticsearchException->json('failures') ?? [], 'status'));
            $this->assertSame(['bad-mapping', 'conflict', 'overloaded'], array_column($elasticsearchException->json('failures'), '_id'));
        }
    }

    public function test_bulk_write_preserves_generated_id_when_a_replica_fails(): void
    {
        [$collection] = $this->movies(json_encode([
            'errors' => false,
            'items' => [['create' => [
                'status' => 201, '_id' => 'generated', '_index' => 'movies-01',
                '_shards' => ['total' => 2, 'successful' => 1, 'failed' => 1],
            ]]],
        ], JSON_THROW_ON_ERROR));

        $document = new Document;
        try {
            $collection->add($document);
            $this->fail('The replica write failed.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(1, $elasticsearchException->json('_shards.failed'));
            $this->assertSame('generated', $document->_id);
            $this->assertSame('movies-01', $document->_index);
        }
    }

    /**
     * @test
     */
    public function listed_and_random_documents_keep_index(): void
    {
        $json = json_encode([
            'hits' => [
                'hits' => [[
                    '_index' => 'movies',
                    '_id' => 'matrix_1',
                    '_score' => 1.0,
                    '_source' => ['title' => 'The Matrix'],
                ]],
            ],
        ], JSON_THROW_ON_ERROR);

        [$collection] = $this->movies($json);

        [$listed] = $collection->take(1);

        $this->assertSame('movies', $listed->_index);
        $this->assertSame('matrix_1', $listed->_id);

        $random = $collection->random(1);

        $this->assertSame('movies', $random->first()->_index);
        $this->assertSame('matrix_1', $random->first()->_id);
    }

    /**
     * @test
     */
    public function replace_returns_generated_id_and_index(): void
    {
        [$collection] = $this->movies('{"items":[{"create":{"_id":"gen","_index":"movies-01","status":201}}]}');

        $doc = $collection->replace(new Document(['title' => 'A']));

        $this->assertSame('gen', $doc->_id);
        $this->assertSame('movies-01', $doc->_index);
    }

    /** @test */
    public function replacing_a_missing_id_creates_the_document(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"index":{"_id":"matrix_1","_index":"movies","status":201,"result":"created"}}]}');

        $doc = $collection->replace(new Document(['title' => 'A'], 'matrix_1'));

        $this->assertSame('matrix_1', $doc->_id);
        $this->assertSame(['index' => ['_id' => 'matrix_1']], $this->ndjson($captured->bodies[0])[0]);
    }

    /** @test */
    public function replace_runs_active_hooks_around_the_write(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"index":{"_id":"1","_index":"movies-01","status":200}}]}');
        $events = [];
        $hook = $this->createMock(CollectionHook::class);
        $hook->method('shouldRun')->willReturn(true);
        $hook->method('beforeBatch')->willReturnCallback(function () use (&$events): void {
            $events[] = 'before';
        });
        $hook->method('processBatch')->willReturnCallback(function (array $docs) use (&$events): array {
            $events[] = 'process';
            $docs[0]->hooked = true;

            return $docs;
        });
        $hook->method('afterBatch')->willReturnCallback(function (array $docs) use (&$events): void {
            $events[] = 'after '.$docs[0]->_index;
        });

        $collection->hooks([$hook])->replace(new Document([], '1'));

        $this->assertSame(['before', 'process', 'after movies-01'], $events);
        $this->assertSame(['hooked' => true], $this->ndjson($captured->bodies[0])[1]);
    }

    /** @test */
    public function add_throws_the_item_conflict_status(): void
    {
        [$collection] = $this->movies('{"errors":true,"items":[{"create":{"status":409,"error":{"type":"version_conflict_engine_exception"}}}]}');

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(409);
        $collection->add(new Document([], 'existing'));
    }

    /** @test */
    public function merge_sets_metadata_on_successes_after_a_failure(): void
    {
        [$collection] = $this->movies(json_encode([
            'errors' => true,
            'items' => [
                ['create' => ['status' => 400, 'error' => ['type' => 'mapper_parsing_exception']]],
                ['create' => ['_id' => 'gen', '_index' => 'movies-01', 'status' => 201]],
                ['update' => ['_id' => 'existing', '_index' => 'movies-01', 'status' => 200]],
            ],
        ]));
        $bad = new Document;
        $created = new Document;
        $updated = new Document([], 'existing');

        try {
            $collection->merge([$bad, $created, $updated]);
            $this->fail('Expected the failed item to throw.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(400, $elasticsearchException->getCode());
        }

        $this->assertFalse(isset($bad->_id));
        $this->assertFalse(isset($bad->_index));
        $this->assertSame('gen', $created->_id);
        $this->assertSame('movies-01', $created->_index);
        $this->assertSame('movies-01', $updated->_index);
    }

    public function test_merge_batches_large_documents_and_reports_failures_across_batches(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $calls = 0;
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use (&$calls): ElasticsearchResponse {
            $calls++;
            if ($request->getBody()->getSize() > 5 * 1024 * 1024) {
                throw new ElasticsearchException(['type' => 'content_too_long'], 413);
            }

            [$metadata, $source] = $this->ndjson((string) $request->getBody());
            $action = array_key_first($metadata);
            $id = $metadata[$action]['_id'] ?? $source['title'];
            $item = $id === 'generated'
                ? ['status' => 201, '_id' => $id, '_index' => 'movies-01']
                : ['status' => 400, '_id' => $id, 'error' => ['type' => 'mapper_parsing_exception']];

            return $request->response(new Response(200, [], json_encode(['items' => [[$action => $item]]])));
        });
        $collection = new AliveCollection('movies', $connection);
        $largeBody = str_repeat('a', 3 * 1024 * 1024);
        $created = new Document(['title' => 'generated', 'body' => $largeBody]);

        try {
            $collection->merge([
                new Document(['body' => $largeBody], 'first'),
                $created,
                new Document(['body' => $largeBody], 'last'),
            ]);
            $this->fail('Expected the failed items to throw.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(400, $elasticsearchException->getCode());
            $this->assertSame(['first', 'last'], array_column($elasticsearchException->json('failures'), '_id'));
        }

        $this->assertSame(3, $calls);
        $this->assertSame('generated', $created->_id);
        $this->assertSame('movies-01', $created->_index);
    }

    public function test_merge_retries_only_the_rejected_batch_on_a_smaller_server_limit(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $attempted = [];
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use (&$attempted): ElasticsearchResponse {
            $lines = $this->ndjson((string) $request->getBody());
            $attempted[] = count($lines);
            if (count($lines) > 2) {
                throw new ElasticsearchException(['type' => 'content_too_long'], 413);
            }

            $item = ['create' => ['status' => 201, '_id' => $lines[1]['title'], '_index' => 'movies']];

            return $request->response(new Response(200, [], json_encode(['items' => [$item]])));
        });
        $docs = [new Document(['title' => 'first']), new Document(['title' => 'second'])];

        (new AliveCollection('movies', $connection))->merge($docs);

        $this->assertSame([4, 2, 2], $attempted);
        $this->assertSame(['first', 'second'], array_column($docs, '_id'));
    }

    public function test_merge_clears_a_combo_only_when_its_source_is_explicitly_cleared(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"update":{"_id":"1","_index":"movies","status":200}}]}');
        $properties = new NewProperties;
        $properties->text('title');
        $properties->combo('summary', ['title'])->semantic(api: 'e', dimensions: 128, accuracy: 1);
        $collection->properties($properties)->apis(['e' => $this->createStub(EmbeddingsApi::class)]);

        $collection->merge([new Document(['status' => 'draft'], '1')]);
        $unrelated = $this->ndjson($captured->bodies[0])[1]['doc'];
        $this->assertSame(['status' => 'draft'], $unrelated);

        foreach ([null, '', []] as $emptyTitle) {
            $collection->merge([new Document(['title' => $emptyTitle], '1')]);
            $patch = $this->ndjson(end($captured->bodies))[1]['doc'];
            $this->assertSame(['title' => $emptyTitle, 'summary' => null, '_embeddings' => ['summary' => null]], $patch);
        }
    }

    public function test_top_level_combo_reads_dotted_sources_in_json_object_lists(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->nested('cast', fn (NewProperties $fields): Keyword => $fields->keyword('actor'));
        $properties->combo('summary', ['cast.actor']);

        $collection->properties($properties)->add(new Document(['cast' => [
            (object) ['actor' => 'Ada'], (object) ['actor' => 'Grace'],
        ]]));

        $source = $this->ndjson($captured->bodies[0])[1];
        $this->assertSame('Ada Grace', $source['summary'] ?? null);
    }

    public function test_semantic_completion_embeds_the_input_without_its_options(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $suggest = Text::fromRaw(['suggest' => [
            'type' => 'completion',
            'contexts' => [['name' => 'genre', 'type' => 'category']],
        ]]);
        $suggest->semantic(api: 'e', dimensions: 128, accuracy: 1);

        $properties->add($suggest);
        $api = new FakeEmbeddingsApi($this->createStub(EmbeddingsApi::class), 10);
        $source = ['suggest' => [
            (object) ['input' => ['Star Trek', 'Star Wars'], 'weight' => 10, 'contexts' => ['genre' => ['Sci-Fi']]],
            ['input' => 'Stargate', 'weight' => 5, 'contexts' => ['genre' => ['Sci-Fi']]],
        ]];

        $collection->properties($properties)->apis(['e' => $api])->add(new Document($source));

        $this->assertSame(['Star Trek Star Wars Stargate'], array_column($api->getBatchEmbedCalls()[0], 'text'));
        $this->assertSame(json_decode(json_encode($source), true), array_intersect_key($this->ndjson($captured->bodies[0])[1], $source));
    }

    public function test_merge_formats_date_range_bounds_in_json_objects(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}',
            '{"docs":[{"_id":"1","found":false}]}',
        );
        $properties = new NewProperties;
        $properties->range('available')->date();

        $collection->properties($properties)->merge([
            new Document(['available' => (object) ['gte' => Carbon::parse('2024-06-15T12:45:00Z'), 'lt' => '2024-07-01']], '1'),
        ]);

        $this->assertSame(['available' => ['gte' => '2024-06-15', 'lt' => '2024-07-01']], $this->ndjson(end($captured->bodies))[1]);
    }

    public function test_mapped_epoch_fields_accept_fractional_timestamps(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->datetime('released')->format('epoch_second');
        $properties->date('archived')->format('epoch_millis');
        $source = ['released' => -0.25, 'archived' => '1234.5'];

        $collection->properties($properties)->add(new Document($source));

        $this->assertSame($source, $this->ndjson($captured->bodies[0])[1]);
    }

    public function test_php_dates_use_the_only_configured_epoch_format(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->add(new Date('archived', ['epoch_millis']));
        $properties->add(new DateTime('released', ['epoch_second']));

        $time = Carbon::parse('1969-12-31T23:59:59.750Z');

        $collection->properties($properties)->add(new Document(['archived' => $time, 'released' => $time]));

        $source = $this->ndjson($captured->bodies[0])[1];
        $this->assertSame(-250, $source['archived']);
        $this->assertSame(-0.25, (float) $source['released']);
    }

    public function test_null_entries_in_mapped_object_lists_survive_the_write(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->nested('cast', fn (NewProperties $fields): Keyword => $fields->keyword('actor'));
        $properties->object('crew', fn (NewProperties $fields): Keyword => $fields->keyword('name'));

        $source = ['cast' => [null, ['actor' => 'Ada']], 'crew' => [['name' => 'Grace'], null]];
        $collection->properties($properties)->add(new Document($source));

        $this->assertSame($source, $this->ndjson($captured->bodies[0])[1]);
    }

    public function test_sparse_scalar_lists_keep_their_values_and_order_on_the_wire(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"update":{"_id":"1","_index":"movies","status":200}}]}');
        $properties = new NewProperties;
        $properties->keyword('tags');
        $properties->object('details', fn (NewProperties $fields): Number => $fields->number('ratings'));
        $collection->properties($properties)->merge([
            new Document(['tags' => [2 => 'drama', 7 => 'noir'], 'details' => ['ratings' => [3 => 5, 1 => 2]]], '1'),
        ]);

        $this->assertSame(['tags' => ['drama', 'noir'], 'details' => ['ratings' => [5, 2]]], $this->ndjson($captured->bodies[0])[1]['doc']);
    }

    public function test_a_disabled_object_keeps_opaque_source_values(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $children = new NewProperties;
        $children->number('rating');

        $properties->add(new Object_('payload', $children, ['enabled' => false]));
        $collection->properties($properties);

        foreach (['opaque', ['rating' => 'untyped'], [17]] as $payload) {
            $collection->add(new Document(['payload' => $payload]));

            $this->assertSame(['payload' => $payload], $this->ndjson(end($captured->bodies))[1]);
        }
    }

    public function test_geojson_points_survive_collection_validation(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->geoPoint('location');

        $source = ['location' => ['type' => 'Point', 'coordinates' => [-71.34, 41.12]]];
        $collection->properties($properties)->add(new Document($source));

        $this->assertSame($source, $this->ndjson($captured->bodies[0])[1]);
    }

    public function test_flat_object_keeps_a_numeric_key_as_an_object_property(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->add(new FlatObject('metadata'));

        $collection->properties($properties);

        $collection->add(new Document(['metadata' => ['0' => 'value']]));

        $numericKey = json_decode(explode("\n", $captured->bodies[0])[1])->metadata;
        $this->assertInstanceOf(stdClass::class, $numericKey);
        $this->assertSame('value', $numericKey->{'0'});

        $collection->add(new Document(['metadata' => [['name' => 'value']]]));
        $objects = json_decode(explode("\n", $captured->bodies[1])[1])->metadata;
        $this->assertIsArray($objects);
        $this->assertSame('value', $objects[0]->name);
    }

    public function test_datetime_strings_keep_second_precision_in_their_utc_offsets(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->datetime('released');

        $source = ['released' => ['2024-01-01T12:34:56+01:00:30', '2024-01-01T12:34:56+010030']];

        $collection->properties($properties)->add(new Document($source));

        $this->assertSame($source, $this->ndjson($captured->bodies[0])[1]);
    }

    public function test_semantic_boost_accepts_a_price_as_its_numeric_factor(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->price('priority');
        $properties->text('summary')->semantic(api: 'e', dimensions: 2, accuracy: 1)
            ->boostedBy('priority')->normalizeVector(false);
        $api = $this->createStub(EmbeddingsApi::class);
        $api->method('maxBatchSize')->willReturn(1);
        $api->method('batchEmbed')->willReturn([['vector' => [3.0, 4.0]]]);

        $collection->properties($properties)->apis(['e' => $api])
            ->add(new Document(['priority' => 2.5, 'summary' => 'Solaris']));

        $source = $this->ndjson($captured->bodies[0])[1];
        $this->assertEquals([[7.5, 10.0]], array_values($source['_embeddings']['summary']));
    }

    public function test_a_searchable_number_accepts_and_embeds_numeric_document_values(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->searchableNumber('code')->semantic(api: 'e', dimensions: 128, accuracy: 1);
        $api = new FakeEmbeddingsApi($this->createStub(EmbeddingsApi::class), 10);

        $collection->properties($properties)->apis(['e' => $api])
            ->add(new Document(['code' => [123, '0042', 1.5]]));

        $this->assertSame(['123 0042 1.5'], array_column($api->getBatchEmbedCalls()[0], 'text'));
        $this->assertSame([123, '0042', 1.5], $this->ndjson($captured->bodies[0])[1]['code']);
    }

    public function test_php_date_range_bounds_use_the_configured_epoch_format(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}',
            '{"docs":[{"_id":"1","found":false}]}',
        );
        $properties = new NewProperties;
        $properties->add((new Range('archived', 'epoch_millis'))->date());
        $properties->add((new Range('released', 'epoch_second'))->date());

        $time = Carbon::parse('1969-12-31T23:59:59.750Z');

        $collection->properties($properties)->merge([
            new Document(['archived' => ['gte' => $time], 'released' => ['lt' => $time]], '1'),
        ]);

        $source = $this->ndjson(end($captured->bodies))[1];
        $this->assertSame(-250, $source['archived']['gte']);
        $this->assertSame(-0.25, (float) $source['released']['lt']);
    }

    public function test_merge_validates_and_sends_a_single_nested_object(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->nested('cast', fn (NewProperties $fields): Keyword => $fields->keyword('actor'));

        $document = new Document(['cast' => ['actor' => 'Sigourney Weaver']]);
        $collection->properties($properties)->merge([$document]);

        $this->assertSame('1', $document->_id);
        $this->assertSame(['cast' => ['actor' => 'Sigourney Weaver']], $this->ndjson($captured->bodies[0])[1]);
    }

    public function test_write_preserves_nested_objects_with_only_a_numeric_field(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->object('director', fn (NewProperties $fields): Keyword => $fields->keyword('0'));
        $properties->nested('cast', fn (NewProperties $fields): Keyword => $fields->keyword('0'));

        $collection->properties($properties)->add(new Document([
            'director' => ['0' => 'Ridley Scott'],
            'cast' => [['0' => 'Sigourney Weaver'], ['0' => 'John Hurt']],
        ]));

        $lines = explode("\n", $captured->bodies[0]);
        $this->assertSame('{"director":{"0":"Ridley Scott"},"cast":[{"0":"Sigourney Weaver"},{"0":"John Hurt"}]}', $lines[1]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('director.0 mapped as keyword must be a string');
        $collection->add(new Document(['director' => ['0' => 123]]));
    }

    public function test_nested_numeric_fields_keep_their_semantic_embeddings_on_the_wire(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->nested('cast', function (NewProperties $fields): void {
            $fields->text('0')->semantic(api: 'e', dimensions: 128, accuracy: 1);
        });
        $api = new FakeEmbeddingsApi($this->createStub(EmbeddingsApi::class), 10);
        $collection->properties($properties)->apis(['e' => $api])
            ->add(new Document(['cast' => [['0' => 'Sigourney Weaver']]]));

        $api->assertBatchEmbedWasCalledWith('Sigourney Weaver');
        $source = json_decode(explode("\n", $captured->bodies[0])[1], flags: JSON_THROW_ON_ERROR);
        $this->assertInstanceOf(stdClass::class, $source->_embeddings->cast);
        $this->assertCount(128, array_values((array) $source->_embeddings->cast->{'0'})[0]);
    }

    public function test_a_mapped_object_accepts_an_empty_list(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->object('credits', fn (NewProperties $fields): Keyword => $fields->keyword('actor'));
        $collection->properties($properties)->add(new Document(['credits' => []]));

        $this->assertSame(['credits' => []], $this->ndjson($captured->bodies[0])[1]);
    }

    public function test_writes_accept_the_date_formats_configured_in_the_mapping(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"1","_index":"movies","status":201}}]}');
        $properties = new NewProperties;
        $properties->date('released')->format('MM/dd/yyyy');
        $properties->datetime('archived')->format('epoch_millis');

        $source = ['released' => '06/15/2024', 'archived' => 1718459130123];
        $collection->properties($properties)->add(new Document($source));

        $this->assertSame($source, $this->ndjson($captured->bodies[0])[1]);
    }

    public function test_a_partial_merge_leaves_omitted_embeddings_out_of_the_update(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"update":{"_id":"1","_index":"movies","status":200}}]}');
        $properties = new NewProperties;
        $properties->text('title')->semantic(api: 'e', dimensions: 128, accuracy: 1);
        $api = $this->createStub(EmbeddingsApi::class);
        $collection->properties($properties)->apis(['e' => $api])
            ->merge([new Document(['status' => 'published'], '1')]);

        $this->assertSame(['status' => 'published'], $this->ndjson($captured->bodies[0])[1]['doc']);
    }

    /** @test */
    public function get_throws_an_mget_item_error(): void
    {
        [$collection] = $this->movies('{"docs":[{"_id":"1","error":{"type":"unavailable_shards_exception"}}]}');

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionMessage('unavailable_shards_exception');
        $collection->get('1');
    }

    /** @test */
    public function get_many_throws_an_mget_item_error(): void
    {
        [$collection] = $this->movies('{"docs":[{"_id":"missing","found":false},{"_id":"1","status":403,"error":{"type":"security_exception"}}]}');

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(403);
        $collection->getMany(['missing', '1']);
    }

    /** @test */
    public function get_many_sends_sparse_ids_as_a_list(): void
    {
        [$collection, $captured] = $this->movies('{"docs":[]}');

        $collection->getMany([2 => 'first', 5 => 'last']);

        $docs = json_decode($captured->bodies[0], true, 512, JSON_THROW_ON_ERROR)['docs'];
        $this->assertSame([['_id' => 'first'], ['_id' => 'last']], $docs);
    }

    /**
     * @test
     */
    public function merge_assigns_generated_ids_in_bulk_order(): void
    {
        [$collection] = $this->movies(json_encode([
            'errors' => false,
            'items' => [
                ['create' => ['_id' => 'gen1', '_index' => 'movies', 'status' => 201]],
                ['create' => ['_id' => 'gen2', '_index' => 'movies', 'status' => 201]],
            ],
        ]));

        $docs = [
            'left' => new Document(['title' => 'A']),
            'right' => new Document(['title' => 'B']),
        ];

        $collection->merge($docs);

        $this->assertSame('gen1', $docs['left']->_id);
        $this->assertSame('gen2', $docs['right']->_id);
    }

    /**
     * @test
     */
    public function a_source_field_named_zero_stays_in_the_bulk_line(): void
    {
        [$collection, $captured] = $this->movies(json_encode([
            'errors' => false,
            'items' => [
                ['create' => ['_id' => '1', '_index' => 'movies', 'status' => 201]],
                ['update' => ['_id' => '2', '_index' => 'movies', 'status' => 200]],
            ],
        ]));
        $props = new NewProperties;
        $props->keyword('0');
        $collection->properties($props)->populateEmbeddings(false);

        $collection->add(new Document(['0' => 'zero']));

        $collection->merge([new Document(['0' => 'zero'], '2')]);

        $this->assertStringContainsString('{"0":"zero"}', $captured->bodies[0]);
        $this->assertStringContainsString('{"doc":{"0":"zero"},"doc_as_upsert":true}', $captured->bodies[1]);
    }

    /**
     * @test
     */
    public function a_failed_replace_without_an_id_throws_the_item_error(): void
    {
        [$collection] = $this->movies(json_encode([
            'errors' => true,
            'items' => [[
                'create' => [
                    '_index' => 'movies',
                    '_id' => 'rejected',
                    'status' => 400,
                    'error' => [
                        'type' => 'illegal_argument_exception',
                        'reason' => 'target index is missing',
                    ],
                ],
            ]],
        ]));

        $doc = new Document(['title' => 'A']);

        try {
            $collection->replace($doc);
            $this->fail('The failed create must throw.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(400, $elasticsearchException->getCode());
            $this->assertSame('illegal_argument_exception', $elasticsearchException->json('type'));
            $this->assertSame('target index is missing', $elasticsearchException->json('reason'));
            $this->assertFalse(isset($doc->_id));
            $this->assertFalse(isset($doc->_index));
        }
    }

    /**
     * @test
     */
    public function an_empty_document_source_is_a_json_object(): void
    {
        [$collection, $captured] = $this->movies(json_encode([
            'errors' => false,
            'items' => [
                ['create' => ['_id' => 'gen', '_index' => 'movies', 'status' => 201]],
            ],
        ]));
        $collection->populateEmbeddings(false);

        $collection->add(new Document);

        $lines = array_values(array_filter(
            explode("\n", $captured->bodies[0]),
            static fn (string $line): bool => trim($line) !== '',
        ));

        $this->assertSame('{}', $lines[1]);

        [$collection, $captured] = $this->movies(json_encode([
            'errors' => false,
            'items' => [
                ['update' => ['_id' => 'matrix_1', '_index' => 'movies', 'status' => 200]],
            ],
        ]));
        $collection->populateEmbeddings(false);

        $collection->merge([new Document([], 'matrix_1')]);

        $this->assertStringContainsString('{"doc":{},"doc_as_upsert":true}', $captured->bodies[0]);
    }

    /**
     * @test
     */
    public function offset_set_stores_the_offset_as_the_document_id(): void
    {
        [$collection, $captured] = $this->movies(json_encode([
            'errors' => false,
            'items' => [
                ['create' => ['_id' => 'matrix_1', '_index' => 'movies', 'status' => 201]],
            ],
        ]));

        $collection['matrix_1'] = new Document(['title' => 'A']);

        $this->assertStringContainsString('"_id":"matrix_1"', $captured->bodies[0]);
    }

    /** @test */
    public function offset_set_accepts_the_same_document_id(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_id":"0","_index":"movies","status":201}}]}');

        $collection[0] = new Document(['title' => 'Zero'], '0');

        $this->assertSame(['create' => ['_id' => '0']], $this->ndjson($captured->bodies[0])[0]);
    }

    /**
     * @test
     */
    public function take_from_the_end_of_an_empty_collection_is_empty(): void
    {
        [$collection, $captured] = $this->movies('{"hits":{"hits":[]}}');

        $this->assertSame([], $collection->take(-1));
        $this->assertCount(3, $captured->bodies);
        $this->assertSame(['id' => 'snapshot'], json_decode($captured->bodies[2], true));
    }

    /**
     * @test
     */
    public function a_nested_list_value_is_validated(): void
    {
        $props = new NewProperties;
        $props->number('rating')->float();
        $props->nested('cast', function (NewProperties $props): void {
            $props->number('screen_time')->integer();
            $props->keyword('role');
        });

        $processor = new DocumentProcessor($props->get());

        $processor->validateFields(new Document([
            'rating' => 1.5,
            'cast' => [
                ['screen_time' => 12, 'role' => 'Neo'],
            ],
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document validation failed: The field cast.screen_time mapped as integer must be a number');

        $processor->validateFields(new Document([
            'cast' => [
                ['screen_time' => 'bad', 'role' => 'Neo'],
            ],
        ]));
    }

    /**
     * @test
     */
    public function a_nested_or_object_container_must_be_an_object(): void
    {
        $props = new NewProperties;
        $props->nested('cast', function (NewProperties $props): void {
            $props->keyword('role');
        });
        $props->object('director', function (NewProperties $props): void {
            $props->number('birth_year')->integer();
        });

        $processor = new DocumentProcessor($props->get());

        $processor->validateFields(new Document([
            'cast' => [
                ['role' => 'Neo'],
            ],
            'director' => ['birth_year' => 1970],
        ]));

        $messages = [];

        foreach ([
            ['cast' => 'nope'],
            ['director' => 'Nolan'],
        ] as $source) {
            try {
                $processor->validateFields(new Document($source));
                $messages[] = 'NO THROW';
            } catch (InvalidArgumentException $exception) {
                $messages[] = $exception->getMessage();
            }
        }

        $this->assertSame([
            'Document validation failed: Nested field cast must be an object.',
            'Document validation failed: Object field director must be an object.',
        ], $messages);
    }

    /**
     * @test
     */
    public function a_flat_object_array_stays_valid_on_the_writer(): void
    {
        $props = new NewProperties;
        $props->add(new FlatObject('metadata'));
        $props->keyword('title');

        $processor = new DocumentProcessor($props->get());

        $document = new Document([
            'metadata' => ['color' => 'red'],
            'title' => ['Neo'],
        ]);

        $this->assertSame($document, $processor->validateFields($document));

        $object = new Document([
            'metadata' => (object) ['color' => 'red'],
            'title' => 'Neo',
        ]);

        $this->assertSame($object, $processor->validateFields($object));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The field title mapped as keyword must be a string');

        $processor->validateFields(new Document([
            'metadata' => ['color' => 'red'],
            'title' => [1],
        ]));
    }

    /**
     * @return array{0: AliveCollection, 1: stdClass}
     */
    private function movies(string $json, ?string $mget = null): array
    {
        $captured = new stdClass;
        $captured->bodies = [];
        $captured->paths = [];

        $connection = new class($json, $captured, $mget) implements ElasticsearchConnection
        {
            public function __construct(private string $json, private stdClass $captured, private ?string $mget) {}

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $this->captured->paths[] = $request->getUri()->getPath();
                $this->captured->bodies[] = (string) $request->getBody();

                $json = match ($request->getUri()->getPath()) {
                    '/movies/_pit' => '{"id":"snapshot"}',
                    '/_pit' => '{"succeeded":true}',
                    '/movies/_mget' => $this->mget ?? $this->json,
                    default => $this->json,
                };

                return $request->response(new Response(200, ['Content-Type' => 'application/json'], $json));
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

        $collection = new class('movies', $connection) extends AliveCollection
        {
            public function deleteIds(array $ids): bool
            {
                return $this->deleteDocuments($this->name, $ids, 'false');
            }
        };

        return [$collection, $captured];
    }

    /** @test */
    public function a_document_field_supports_property_isset_and_unset(): void
    {
        $doc = new Document(['price' => 0, 'note' => null]);

        $this->assertTrue(isset($doc->price));
        $this->assertFalse(isset($doc->note));
        $this->assertSame(0, $doc->price ?? -1);

        unset($doc->price);

        $this->assertNull($doc->price);
        $this->assertSame(['note' => null], $doc->_source);
    }

    /** @test */
    public function appending_source_entries_keeps_each_value(): void
    {
        $doc = new Document(['' => 'named field']);

        $doc[] = 'first';
        $doc[] = 'second';

        $this->assertSame(['' => 'named field', 0 => 'first', 1 => 'second'], $doc->_source);
    }

    /** @test */
    public function removing_no_ids_is_a_successful_no_op(): void
    {
        [$collection, $captured] = $this->movies('{"items":[]}');

        $this->assertTrue($collection->remove([]));
        $this->assertSame([], $captured->bodies);
    }

    /** @test */
    public function getting_no_ids_returns_an_empty_list_without_multiget(): void
    {
        [$collection, $captured] = $this->movies('{"docs":[]}');

        $this->assertSame([], $collection->getMany([]));
        $this->assertSame([], $captured->bodies);
    }

    public function test_sparse_source_filters_remain_field_lists(): void
    {
        [$collection, $captured] = $this->movies('{"hits":{"hits":[]}}');
        $collection->only([4 => 'title'])->except([7 => 'secret'])->random(1);

        $source = json_decode($captured->bodies[0])->_source;
        $this->assertSame(['title'], $source->includes);
        $this->assertSame(['secret'], $source->excludes);
    }

    public function test_multiget_preserves_literal_commas_in_field_names(): void
    {
        [$collection, $captured] = $this->movies('{"docs":[]}');
        $collection->except(['name,private'])->get('1');

        $item = json_decode($captured->bodies[0], true)['docs'][0];
        $this->assertSame(['excludes' => ['name,private']], $item['_source'] ?? null);
    }

    public function test_keyed_semantic_batch_assigns_embeddings_to_the_document(): void
    {
        [$collection] = $this->movies('{"items":[{"create":{"_index":"movies","_id":"1","status":201}}]}');
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', accuracy: 1, dimensions: 128);
        $api = new FakeEmbeddingsApi($this->createStub(EmbeddingsApi::class), 10);
        $document = new Document(['title' => 'Matrix']);

        $collection->properties($props)->apis(['e' => $api])->merge(['matrix' => $document]);

        $this->assertNotEmpty($document->_source['_embeddings']);
    }

    public function test_copying_a_document_to_another_index_updates_its_write_metadata(): void
    {
        [$collection] = $this->movies('{"items":[{"create":{"_index":"movies","_id":"1","status":201}}]}');
        $document = Document::fromRaw(['_id' => '1', '_index' => 'archive', '_source' => ['title' => 'Matrix']]);

        $this->assertSame('movies', $collection->add($document)->_index);
    }

    public function test_assigning_a_document_to_a_different_offset_creates_the_requested_id(): void
    {
        [$collection, $captured] = $this->movies('{"items":[{"create":{"_index":"movies","_id":"copy","status":201}}]}');
        $original = new Document(['title' => 'Matrix'], 'original');
        $collection['copy'] = $original;

        $this->assertSame('original', $original->_id);
        $this->assertSame([['create' => ['_id' => 'copy']], ['title' => 'Matrix']], $this->ndjson($captured->bodies[0]));
    }

    public function test_dotted_document_reads_traverse_json_objects(): void
    {
        $document = new Document(['credits' => [(object) ['actor' => 'Keanu']]]);
        $this->assertSame('Keanu', $document['credits.0.actor']);
        $this->assertSame('Keanu', $document->get('credits.0.actor'));
    }

    public function test_serialized_document_source_is_a_json_object(): void
    {
        foreach ([new Document, new Hit(['0' => 'zero'], '1', null)] as $document) {
            $source = json_decode(json_encode($document, JSON_THROW_ON_ERROR))->_source;
            $this->assertInstanceOf(stdClass::class, $source);
            $this->assertSame($document->_source, (array) $source);
        }
    }

    public function test_clear_refreshes_pending_writes_before_selecting_documents(): void
    {
        [$collection, $captured] = $this->movies('{}');
        $collection->clear();

        $this->assertSame(['/movies/_refresh', '/movies/_delete_by_query'], $captured->paths);
    }

    public function test_dot_only_ids_use_document_body_operations(): void
    {
        [$collection, $captured] = $this->movies('{"docs":[],"items":[{"delete":{"status":200,"result":"deleted"}}]}');
        $this->assertFalse($collection->has('..'));
        $this->assertTrue($collection->remove('.'));
        $this->assertSame(['/movies/_mget', '/movies/_bulk'], $captured->paths);
        $this->assertSame('..', json_decode($captured->bodies[0], true)['docs'][0]['_id']);
        $this->assertSame('.', $this->ndjson($captured->bodies[1])[0]['delete']['_id']);
    }

    public function test_a_single_write_accepts_a_rekeyed_hook_result(): void
    {
        [$collection] = $this->movies('{"items":[{"create":{"_index":"movies","_id":"1","status":201}}]}');
        $hook = $this->createStub(CollectionHook::class);
        $hook->method('shouldRun')->willReturn(true);
        $hook->method('processBatch')->willReturn([7 => new Document(['title' => 'Enriched'])]);

        $document = $collection->hooks([$hook])->add(new Document);
        $this->assertSame('Enriched', $document['title']);
        $this->assertSame('1', $document->_id);
    }

    private function ndjson(string $body): array
    {
        $rows = [];

        foreach (explode("\n", $body) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }

        return $rows;
    }

    public function test_validates_a_numeric_field_name_at_the_document_root(): void
    {
        $props = new NewProperties;
        $props->number('0');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document validation failed: The field 0');

        (new DocumentProcessor($props()))->validateFields(new Document(['0' => 'invalid']));
    }

    public function test_filtered_source_fields_are_lists(): void
    {
        [$collection, $captured] = $this->movies('{"hits":{"hits":[]}}');
        $collection->only([2 => 'title'])->except([3 => 'secret'])->random();

        $source = json_decode($captured->bodies[0])->_source;
        $this->assertSame(['title'], $source->includes);
        $this->assertSame(['secret'], $source->excludes);
    }

    public function test_document_source_encodes_as_an_object(): void
    {
        foreach ([new Document([]), new Hit(['0' => 'zero'], '1', null)] as $document) {
            $source = json_decode(json_encode($document))->_source;
            $this->assertInstanceOf(stdClass::class, $source);
            $this->assertSame($document->_source, (array) $source);
            $raw = $document->jsonSerialize() + ['_index' => 'films'];
            $this->assertSame($document->_source, $document::fromRaw($raw)->_source);
        }
    }

    public function test_single_document_rethrows_a_payload_too_large_error(): void
    {
        $error = new ElasticsearchException(['type' => 'content_too_long_exception'], 413);
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->expects($this->once())->method('__invoke')->willThrowException($error);

        $this->expectExceptionObject($error);
        (new AliveCollection('movies', $connection))->add(new Document(['title' => 'Matrix']));
    }

    public function test_bulk_item_error_never_assigns_document_identity(): void
    {
        [$collection] = $this->movies('{"items":[{"create":{"status":201,"_id":"1","_index":"movies","error":{"type":"write_failed","reason":"Write failed"}}}]}');
        $document = new Document(['title' => 'Matrix']);

        try {
            $collection->add($document);
            $this->fail('Expected a bulk item exception.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame('write_failed', $elasticsearchException->json('type'));
            $this->assertFalse(isset($document->_id));
            $this->assertFalse(isset($document->_index));
        }
    }

    public function test_merge_without_an_alias_uses_upsert_when_patch_index_is_missing(): void
    {
        [$collection, $captured] = $this->movies(
            '{"items":[{"update":{"status":201,"_id":"1","_index":"movies"}}]}',
            '{"docs":[{"_id":"1","error":{"type":"index_not_found_exception"}}]}',
        );
        $properties = new NewProperties;
        $properties->keyword('title');
        $properties->combo('label', ['title']);

        $collection->properties($properties)->merge([new Document(['title' => 'Matrix'], '1')]);

        $this->assertSame(['/movies/_mget', '/movies/_bulk'], $captured->paths);
        $this->assertSame([
            ['update' => ['_id' => '1']],
            ['doc' => ['title' => 'Matrix', 'label' => 'Matrix'], 'doc_as_upsert' => true],
        ], $this->ndjson($captured->bodies[1]));
    }

    public function test_merge_reports_patch_item_errors_before_writing(): void
    {
        [$collection, $captured] = $this->movies('{}', '{"docs":[{"_id":"1","status":403,"error":{"type":"security_exception"}}]}');
        $properties = new NewProperties;
        $properties->keyword('title');
        $properties->combo('label', ['title']);

        try {
            $collection->properties($properties)->merge([new Document(['title' => 'Matrix'], '1')]);
            $this->fail('Expected a patch read exception.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(403, $elasticsearchException->getCode());
            $this->assertSame('security_exception', $elasticsearchException->json('type'));
            $this->assertSame(['/movies/_mget'], $captured->paths);
        }
    }

    public function test_merge_rejects_a_patch_for_a_document_without_source(): void
    {
        [$collection, $captured] = $this->movies('{}', '{"docs":[{"_id":"1","found":true}]}');
        $properties = new NewProperties;
        $properties->keyword('title');
        $properties->combo('label', ['title']);

        try {
            $collection->properties($properties)->merge([new Document(['title' => 'Matrix'], '1')]);
            $this->fail('Expected a missing source exception.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(400, $elasticsearchException->getCode());
            $this->assertSame('illegal_argument_exception', $elasticsearchException->json('type'));
            $this->assertSame("Cannot patch document '1' without _source.", $elasticsearchException->json('reason'));
            $this->assertSame(['/movies/_mget'], $captured->paths);
        }
    }

    public function test_patch_merges_sources_when_embedding_generation_is_disabled(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic('model');
        $patch = new Document(['title' => 'updated']);
        $processor = new DocumentProcessor($props->get());
        $this->assertSame([], $processor->applyPatch($patch, ['status' => 'open'], false));
        $this->assertSame(['status' => 'open', 'title' => 'updated'], $patch->_source);
    }

    public function test_partial_embedding_batch_embeds_plain_completion_inputs_only_for_selected_fields(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic('model', dimensions: 8);
        $props->text('suggest')->completion()->semantic('model', dimensions: 8, accuracy: 1);
        $api = new FakeEmbeddingsApi($this->createStub(EmbeddingsApi::class), 10);
        $processor = (new DocumentProcessor($props->get()))->apis(['model' => $api]);
        $doc = new Document(['title' => 'untouched', 'suggest' => ['Star Trek', 'Stargate'], '_embeddings' => ['title' => ['v' => [1]]]]);
        $processor->populateEmbeddingsBatch([$doc], [['fields' => ['suggest'], 'embeddings' => new Document([])]]);
        $this->assertSame(['Star Trek Stargate'], array_column($api->getBatchEmbedCalls()[0], 'text'));
        $this->assertSame(['v' => [1]], $doc['_embeddings']['title']);
    }

    public function test_literal_dotted_date_leaves_an_unmapped_prefix_untouched(): void
    {
        $props = new NewProperties;
        $props->date('event.date');

        $date = Carbon::parse('2026-01-02');
        $doc = new Document(['event.date' => $date, 'event' => ['date' => $date]]);
        (new DocumentProcessor($props->get()))->formatDateTimeFields($doc);
        $this->assertSame('2026-01-02', $doc->_source['event.date']);
        $this->assertSame($date, $doc->_source['event']['date']);
    }

    public function test_combo_under_a_dotted_container_skips_unmapped_prefixes(): void
    {
        $props = new NewProperties;
        $props->object('order.item', function (NewProperties $item): void {
            $item->keyword('name');
            $item->combo('label', ['name']);
        });
        $doc = new Document(['order.item' => ['name' => 'book'], 'order' => ['item' => ['name' => 'other']]]);
        (new DocumentProcessor($props->get()))->populateComboFields($doc);
        $this->assertSame('book', $doc->_source['order.item']['label']);
        $this->assertSame(['item' => ['name' => 'other']], $doc->_source['order']);
    }
}
