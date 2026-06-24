<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use ArrayAccess;
use Carbon\Carbon;
use Countable;
use DateTime;
use InvalidArgumentException;
use IteratorAggregate;
use Sigmie\Document\Contracts\CollectionHook;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class AliveCollectionTest extends TestCase
{
    /**
     * @test
     */
    public function take(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['foo' => 'bar'], '4'),
            new Document(['foo' => 'baz']),
            new Document(['foo' => 'baz']),
            new Document(['foo' => 'baz']),
            new Document(['foo' => 'baz']),
            new Document(['foo' => 'baz']),
            new Document(['foo' => 'baz']),
            new Document(['foo' => 'baz']),
            new Document(['foo' => 'baz']),
            new Document(['foo' => 'baz']),
            new Document(['baz' => 'john'], '2'),
        ];

        $index->merge($docs);

        $docs = $index->take(1);

        $this->assertCount(1, $docs);
        $this->assertEquals('4', $docs[0]->_id);

        $docs = $index->take(-1);

        $this->assertCount(1, $docs);
        $this->assertEquals('2', $docs[0]->_id);
    }

    /**
     * @test
     */
    public function index_delete_method(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->create();

        $this->assertIndexExists($indexName);

        $index->delete();

        $this->assertIndexNotExists($indexName);
    }

    /**
     * @test
     */
    public function lazy_each(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['foo' => 'bar'], '4'),
            new Document(['foo' => 'baz'], '89'),
            new Document(['baz' => 'john'], '2'),
        ];

        $index->merge($docs);
        $index = $index->chunk(1);

        $count = 0;

        $index->each(function (Document $document, string $_index) use (&$count): void {
            $count++;
        });

        $this->assertEquals(3, $count);

        $index = $index->chunk(2);

        $count = 0;

        $index->each(function (Document $document, string $_index) use (&$count): void {
            $count++;
        });

        $this->assertEquals(3, $count);

        $index = $index->chunk(3);

        $count = 0;

        $index->each(function (Document $document, string $_index) use (&$count): void {
            $count++;
        });

        $this->assertEquals(3, $count);
    }

    /**
     * @test
     */
    public function mass_delete_docs(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [new Document(['bar' => 'foo'], '1'), new Document(['foo' => 'bar'], '2')];

        $index->merge($docs);

        $this->assertCount(2, $index);

        $index->remove('1');
        $index->remove('2');

        $this->assertCount(0, $index);
    }

    /**
     * @test
     */
    public function remove_accepts_multiple_ids_and_deletes_them_from_elasticsearch(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, true);

        $index->merge([
            new Document(['foo' => 'bar'], 'first'),
            new Document(['foo' => 'baz'], 'second'),
            new Document(['foo' => 'qux'], 'third'),
        ]);

        $this->assertTrue($index->remove(['first', 'third']));

        $this->assertNull($index->get('first'));
        $this->assertSame('baz', $index->get('second')->foo);
        $this->assertNull($index->get('third'));
        $this->assertCount(1, $index);
    }

    /**
     * @test
     */
    public function add_or_update(): void
    {
        $indexName = uniqid();

        $index = $this->sigmie->collect($indexName, true);

        $document = new Document(['foo' => 'bar'], 'id');

        $index->add($document);

        $document->foo = 'john';

        $index->merge([$document]);

        $doc = $index['id'];

        $this->assertEquals($doc->foo, 'john');
    }

    /**
     * @test
     */
    public function replace_updates_and_creates_documents_in_elasticsearch(): void
    {
        $indexName = uniqid();

        $index = $this->sigmie->collect($indexName, true);

        $index->add(new Document(['foo' => 'old', 'category' => 'docs'], 'existing'));

        $updated = $index->replace(new Document(['foo' => 'new', 'category' => 'docs'], 'existing'));
        $created = $index->replace(new Document(['foo' => 'generated', 'category' => 'notes']));

        $this->assertSame('existing', $updated->_id);
        $this->assertNotEmpty($created->_id);

        $updatedFromElasticsearch = $index->get('existing');
        $createdFromElasticsearch = $index->get($created->_id);

        $this->assertSame('new', $updatedFromElasticsearch->foo);
        $this->assertSame('generated', $createdFromElasticsearch->foo);
        $this->assertSame('notes', $createdFromElasticsearch->category);
        $this->assertCount(2, $index);
    }

    /**
     * @test
     */
    public function hooks_process_added_and_merged_documents_before_elasticsearch_write(): void
    {
        $indexName = uniqid();
        $hook = new class implements CollectionHook
        {
            public int $beforeBatches = 0;

            public int $afterBatches = 0;

            public function shouldRun(Properties $properties): bool
            {
                return true;
            }

            public function beforeBatch(string $indexName, Sigmie $sigmie, Properties $properties, array $apis): void
            {
                $this->beforeBatches++;
            }

            public function processBatch(array $documents, Properties $properties, array $apis): array
            {
                return array_map(function (Document $document): Document {
                    $document->hooked = true;

                    return $document;
                }, $documents);
            }

            public function afterBatch(array $documents, string $indexName, Sigmie $sigmie, Properties $properties, array $apis): void
            {
                $this->afterBatches++;
            }
        };

        $index = $this->sigmie->collect($indexName, true)->hooks([$hook]);

        $index->add(new Document(['title' => 'Single'], 'single'));
        $index->merge([new Document(['title' => 'Batch'], 'batch')]);

        $single = $index->get('single');
        $batch = $index->get('batch');

        $this->assertTrue($single->hooked);
        $this->assertTrue($batch->hooked);
        $this->assertSame(2, $hook->beforeBatches);
        $this->assertSame(2, $hook->afterBatches);
    }

    /**
     * @test
     */
    public function without_hooks_writes_original_document_to_elasticsearch(): void
    {
        $indexName = uniqid();
        $hook = new class implements CollectionHook
        {
            public function shouldRun(Properties $properties): bool
            {
                return true;
            }

            public function beforeBatch(string $indexName, Sigmie $sigmie, Properties $properties, array $apis): void {}

            public function processBatch(array $documents, Properties $properties, array $apis): array
            {
                return array_map(function (Document $document): Document {
                    $document->hooked = true;

                    return $document;
                }, $documents);
            }

            public function afterBatch(array $documents, string $indexName, Sigmie $sigmie, Properties $properties, array $apis): void {}
        };

        $index = $this->sigmie->collect($indexName, true)
            ->hooks([$hook])
            ->withoutHooks();

        $index->add(new Document(['title' => 'Original'], 'original'));

        $stored = $index->get('original');

        $this->assertSame('Original', $stored->title);
        $this->assertArrayNotHasKey('hooked', $stored->_source);
    }

    /**
     * @test
     */
    public function collection_helpers_update_visible_elasticsearch_documents(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName)
            ->properties($blueprint)
            ->populateEmbeddings(false);

        $index->add(new Document(['title' => 'Manual refresh'], 'manual-refresh'));
        $index->refresh();

        $stored = $index->get('manual-refresh');

        $this->assertSame('Manual refresh', $stored->title);
        $this->assertArrayNotHasKey('_embeddings', $stored->_source);

        $index['array-access'] = new Document(['title' => 'Array access'], 'array-access');
        $index->refresh();

        $documents = $index->toArray();

        $this->assertArrayHasKey('array-access', $documents);
        $this->assertSame('Array access', $documents['array-access']->title);

        unset($index['array-access']);
        $index->refresh();

        $this->assertNull($index->get('array-access'));
    }

    /**
     * @test
     */
    public function get_many_respects_source_includes_and_excludes_from_elasticsearch(): void
    {
        $indexName = uniqid();

        $index = $this->sigmie->collect($indexName, true);

        $index->merge([
            new Document(['title' => 'Alpha', 'secret' => 'hidden', 'category' => 'docs'], 'alpha'),
            new Document(['title' => 'Beta', 'secret' => 'hidden', 'category' => 'notes'], 'beta'),
        ]);

        $onlyDocs = $index->only(['title'])->getMany(['alpha', 'beta']);

        $this->assertSame('Alpha', $onlyDocs[0]->_source['title']);
        $this->assertArrayNotHasKey('secret', $onlyDocs[0]->_source);
        $this->assertArrayNotHasKey('category', $onlyDocs[1]->_source);

        $exceptDocs = $this->sigmie->collect($indexName, true)
            ->except(['secret'])
            ->getMany(['alpha', 'beta']);

        $this->assertSame('docs', $exceptDocs[0]->_source['category']);
        $this->assertSame('Beta', $exceptDocs[1]->_source['title']);
        $this->assertArrayNotHasKey('secret', $exceptDocs[0]->_source);
        $this->assertArrayNotHasKey('secret', $exceptDocs[1]->_source);
    }

    /**
     * @test
     */
    public function add_merge_and_empty_merge_id_paths_use_elasticsearch(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $collection = $this->sigmie->collect($indexName, true)
            ->properties($blueprint);

        $created = $collection->add(new Document(['title' => 'Assigned add id']));

        $this->assertNotEmpty($created->_id);
        $this->assertStringStartsWith($indexName, $created->_index);
        $this->assertSame('Assigned add id', $collection->get($created->_id)->_source['title']);

        $merged = new Document(['title' => 'Assigned merge id']);

        $this->assertSame($collection, $collection->merge([$merged]));
        $this->assertNotEmpty($merged->_id);
        $this->assertStringStartsWith($indexName, $merged->_index);
        $this->assertSame('Assigned merge id', $collection->get($merged->_id)->_source['title']);
        $this->assertSame($collection, $collection->merge([]));
        $this->assertSame(2, $collection->count());
    }

    /**
     * @test
     */
    public function offset_unset(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->create();

        $index = $this->sigmie->collect($indexName, true);

        $index->add(new Document(['foo' => 'bar'], '4'));

        $this->assertCount(1, $index);

        $index->remove('4');

        $this->assertCount(0, $index);
    }

    /**
     * @test
     */
    public function offset_exists(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->create();

        $index = $this->sigmie->collect($indexName, true);

        $index->add(new Document(['foo' => 'bar'], '4'));

        $this->assertTrue($index->offsetExists('4'));
        $this->assertFalse($index->offsetExists('6'));
    }

    /**
     * @test
     */
    public function offset_set(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->create();

        $index = $this->sigmie->collect($indexName, true);

        $index->add(new Document(['foo' => 'bar'], '4'));

        $doc = new Document(['foo' => 'baz'], '89');

        $index->add($doc);

        $this->assertCount(2, $index);
        $this->assertNotNull($index['89']);
        $this->assertNull($index['10']);
    }

    /**
     * @test
     */
    public function offset_get(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, true);

        $index->add(new Document(['foo' => 'bar'], '4'));

        $docs = [
            new Document(['foo' => 'bar'], '4'),
            new Document(['foo' => 'baz'], '89'),
            new Document(['baz' => 'john'], '2'),
        ];

        $index->merge($docs);

        $doc = $index->offsetGet('4');

        $this->assertEquals('bar', $doc->foo);
        $this->assertEquals('4', $doc->_id);
    }

    /**
     * @test
     */
    public function remove_document(): void
    {
        $indexName = uniqid();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['foo' => 'bar'], '4'),
            new Document(['foo' => 'bar'], '89'),
            new Document(['baz' => 'john'], '2'),
        ];

        $index->merge($docs);

        $this->assertCount(3, $index);

        $index->remove('89');

        $this->assertCount(2, $index);
    }

    /**
     * @test
     */
    public function index_clear_and_is_empty(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['foo' => 'bar']),
            new Document(['baz' => 'john'], '2'),
        ];

        $index->merge($docs);

        $index->clear();

        $this->assertTrue(count($index) === 0);
        $this->assertTrue($index->isEmpty());
        $this->assertFalse($index->isNotEmpty());
    }

    /**
     * @test
     */
    public function add_documents_accepts_collection_or_array(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['foo' => 'bar']),
            new Document(['baz' => 'john'], '2'),
        ];

        $index->merge($docs);

        $docs = [
            new Document(['foo' => 'bar']),
            new Document(['baz' => 'john'], '3'),
        ];

        $index->merge($docs);

        $this->assertCount(4, $index);
    }

    /**
     * @test
     */
    public function add_document_assigns_id(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, true);

        $doc = new Document(['foo' => 'bar']);

        $this->assertFalse(isset($docs->_id));

        $index->add($doc);

        $this->assertTrue(isset($doc->_id));
    }

    /**
     * @test
     */
    public function index_collection_keys(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['foo' => 'bar']),
            new Document(['baz' => 'john'], '2'),
        ];

        $this->assertFalse(isset($docs[0]->_id));

        $index->merge($docs);

        $this->assertTrue(isset($docs[0]->_id));
        $this->assertEquals(2, $docs[1]->_id);
    }

    /**
     * @test
     */
    public function index_collection_methods(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['foo' => 'bar']),
            new Document(['baz' => 'john']),
            new Document(['baz' => 'john']),
        ];

        $this->assertFalse(isset($docs[0]->_id));

        $index->merge($docs);

        $this->assertTrue(isset($docs[0]->_id));
    }

    /**
     * @test
     */
    public function index_interfaces(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, true);

        $this->assertInstanceOf(Countable::class, $index);
        $this->assertInstanceOf(ArrayAccess::class, $index);
        $this->assertInstanceOf(IteratorAggregate::class, $index);
    }

    /**
     * @test
     */
    public function random(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs1 = [
            new Document(['title' => 'First Document'], '1'),
            new Document(['title' => 'Second Document'], '2'),
            new Document(['title' => 'Third Document'], '3'),
            new Document(['title' => 'Fourth Document'], '4'),
            new Document(['title' => 'Fifth Document'], '5'),
            new Document(['title' => 'Sixth Document'], '6'),
            new Document(['title' => 'Seventh Document'], '7'),
            new Document(['title' => 'Eighth Document'], '8'),
            new Document(['title' => 'Ninth Document'], '9'),
            new Document(['title' => 'Tenth Document'], '10'),
            new Document(['name' => 'Alice'], '11'),
        ];

        $index->merge($docs1);

        $docs1 = $index->random(2);

        $this->assertCount(2, $docs1);

        $docs2 = $index->random(2);

        $this->assertCount(2, $docs2);

        $this->assertNotEquals($docs1, $docs2);
    }

    /**
     * @test
     */
    public function only(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['foo' => 'bar'], '4'),
            new Document(['foo' => 'baz'], '89'),
            new Document(['baz' => 'john'], '2'),
        ];

        $index->merge($docs);

        $index = $index->chunk(1)->only(['foo']);

        $doc = iterator_to_array($index->all())['2'];

        $this->assertEmpty($doc->_source);

        $doc = $index->get('2');

        $this->assertEmpty($doc->_source);

        $docs = $index->take(3);

        $firstDoc = null;
        foreach ($docs as $doc) {
            if ($doc->_id === '2') {
                $firstDoc = $doc;
                break;
            }
        }

        $this->assertEmpty($firstDoc->_source);
    }

    /**
     * @test
     */
    public function except(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['foo' => 'bar'], '4'),
            new Document(['foo' => 'baz'], '89'),
            new Document(['baz' => 'john'], '2'),
        ];

        $index->merge($docs);

        $index = $index->chunk(1)->except(['foo']);

        $all = iterator_to_array($index->all());

        $this->assertArrayNotHasKey('foo', $all['4']->_source);
        $this->assertArrayNotHasKey('foo', $all['89']->_source);
        $this->assertArrayNotHasKey('foo', $all['2']->_source);
        $this->assertArrayHasKey('baz', $all['2']->_source);
    }

    /**
     * @test
     */
    public function auto_format_datetime_to_datetime_string(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->datetime('created_at');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $index = $this->sigmie->collect($indexName, true)->properties($blueprint);

        $carbonDate = Carbon::create(2023, 4, 7, 12, 38, 29, 'UTC');
        $phpDate = new DateTime('2023-04-08 15:20:10+02:00');

        $index->add(new Document(['title' => 'Test Carbon', 'created_at' => $carbonDate]));
        $index->add(new Document(['title' => 'Test DateTime', 'created_at' => $phpDate]));

        $docs = $index->take(2);

        $this->assertCount(2, $docs);

        $doc1 = $docs[0];
        $doc2 = $docs[1];

        $this->assertIsString($doc1->created_at);
        $this->assertIsString($doc2->created_at);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}$/', $doc1->created_at);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}$/', $doc2->created_at);
    }

    /**
     * @test
     */
    public function auto_format_datetime_to_date_string(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->date('birth_date');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $index = $this->sigmie->collect($indexName, true)->properties($blueprint);

        $carbonDate = Carbon::create(2023, 4, 7, 12, 38, 29, 'UTC');
        $phpDate = new DateTime('2023-04-08 15:20:10+02:00');

        $index->add(new Document(['title' => 'Test Carbon', 'birth_date' => $carbonDate]));
        $index->add(new Document(['title' => 'Test DateTime', 'birth_date' => $phpDate]));

        $docs = $index->take(2);

        $this->assertCount(2, $docs);

        $doc1 = $docs[0];
        $doc2 = $docs[1];

        $this->assertIsString($doc1->birth_date);
        $this->assertIsString($doc2->birth_date);

        $this->assertEquals('2023-04-07', $doc1->birth_date);
        $this->assertEquals('2023-04-08', $doc2->birth_date);
    }

    /**
     * @test
     */
    public function validation_fails_for_invalid_datetime(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->datetime('created_at');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $index = $this->sigmie->collect($indexName, true)->properties($blueprint);

        $this->expectException(InvalidArgumentException::class);

        $index->add(new Document(['title' => 'Test', 'created_at' => '2023-04-07']));
    }

    /**
     * @test
     */
    public function validation_fails_for_invalid_date(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->date('birth_date');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $index = $this->sigmie->collect($indexName, true)->properties($blueprint);

        $this->expectException(InvalidArgumentException::class);

        $index->add(new Document(['title' => 'Test', 'birth_date' => '2023-04-07T12:38:29.000000Z']));
    }

    /**
     * @test
     */
    public function validation_passes_for_correct_formats(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->date('birth_date');
        $blueprint->datetime('created_at');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $index = $this->sigmie->collect($indexName, true)->properties($blueprint);

        $index->add(new Document([
            'title' => 'Test',
            'birth_date' => '2023-04-07',
            'created_at' => '2023-04-07T12:38:29.000000Z',
        ]));

        $this->assertCount(1, $index);
    }
}
