<?php

declare(strict_types=1);

namespace Sigmie\Base\Tests\Index;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Documents\Collection as DocumentCollection ;
use Sigmie\Testing\TestCase;

class IndexTest extends TestCase
{
    /**
     * @test
     */
    public function lazy_each()
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->withoutMappings()->create();

        $index = $this->sigmie->collect($indexName, 'true');

        $docs = [
            new Document(['foo' => 'bar'], '4'),
            new Document(['foo' => 'baz'], '89'),
            new Document(['baz' => 'john'], '2'),
        ];

        $index->merge($docs);
        $index = $index->chunk(1);

        $count = 0;

        $index->each(function (Document $document, string $_index) use (&$count) { $count++;
        });

        $this->assertEquals(3, $count);

        $index = $index->chunk(2);

        $count = 0;

        $index->each(function (Document $document, string $_index) use (&$count) {
            $count++;
        });

        $this->assertEquals(3, $count);

        $index = $index->chunk(3);

        $count = 0;

        $index->each(function (Document $document, string $_index) use (&$count) {
            $count++;
        });

        $this->assertEquals(3, $count);
    }

    /**
     * @test
     */
    public function mass_delete_docs()
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->withoutMappings()->create();

        $index = $this->sigmie->collect($indexName, 'true');

        $docs = [new Document(['bar' => 'foo'], '1'), new Document(['foo' => 'bar'], '2')];

        $index->merge($docs,);

        $this->assertCount(2, $index);

        $index->remove('1');
        $index->remove('2');

        $this->assertCount(0, $index);
    }

    /**
     * @test
     */
    public function add_or_update()
    {
        $indexName = uniqid();

        $index = $this->sigmie->collect($indexName, 'true');

        $document = new Document(['foo' => 'bar'], 'id');

        $index->add($document, 'true');

        $document->foo = 'john';

        $index->merge([$document]);

        $doc = $index['id'];

        $this->assertEquals($doc->foo, 'john');
    }

    /**
     * @test
     */
    public function offset_unset()
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->withoutMappings()->create();

        $index = $this->sigmie->collect($indexName, 'true');

        $index->add(new Document(['foo' => 'bar'], '4'), 'true');

        $this->assertCount(1, $index);

        $index->remove('4');

        $this->assertCount(0, $index);
    }

    /**
     * @test
     */
    public function offset_exists()
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->withoutMappings()->create();

        $index = $this->sigmie->collect($indexName, 'true');

        $index->add(new Document(['foo' => 'bar'], '4'),);

        $this->assertTrue($index->offsetExists('4'));
        $this->assertFalse($index->offsetExists('6'));
    }

    /**
     * @test
     */
    public function offset_set()
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->withoutMappings()->create();

        $index = $this->sigmie->collect($indexName, 'true');

        $index->add(new Document(['foo' => 'bar'], '4'),);

        $doc = new Document(['foo' => 'baz'], '89');

        $index->add($doc);

        $this->assertCount(2, $index);
        $this->assertNotNull($index['89']);
        $this->assertNull($index['10']);
    }

    /**
     * @test
     */
    public function offset_get()
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, 'true');

        $index->add(new Document(['foo' => 'bar'], '4'),);

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
    public function remove_document()
    {
        $indexName = uniqid();

        $index = $this->sigmie->collect($indexName, 'true');

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
    public function index_clear_and_is_empty()
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, 'true');

        $docs = [
            new Document(['foo' => 'bar']),
            new Document(['baz' => 'john'], '2'),
        ];

        $index->merge($docs);

        $index->clear('true');

        $this->assertTrue(count($index) === 0);
        $this->assertTrue($index->isEmpty());
        $this->assertFalse($index->isNotEmpty());
    }

    /**
     * @test
     */
    public function add_documents_accepts_collection_or_array()
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, 'true');

        $docs = [
            new Document(['foo' => 'bar']),
            new Document(['baz' => 'john'], '2'),
        ];

        $index->merge($docs);

        $docs = [
            new Document(['foo' => 'bar']),
            new Document(['baz' => 'john'], '3'),
        ];

        $index->merge($docs, 'true');

        $this->assertCount(4, $index);
    }

    /**
     * @test
     */
    public function add_document_assigns_id()
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, 'true');

        $doc = new Document(['foo' => 'bar']);

        $this->assertNull($doc->_id);

        $index->add($doc);

        $this->assertNotNull($doc->_id);
    }

    /**
     * @test
     */
    public function index_collection_keys()
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, 'true');

        $docs = new DocumentCollection([
            new Document(['foo' => 'bar']),
            new Document(['baz' => 'john'], '2'),
        ]);

        $this->assertNull($docs[0]->_id);

        $index->merge($docs->toArray());

        $this->assertNotNull($docs[0]->_id);
        $this->assertEquals(2, $docs[1]->_id);
    }

    /**
     * @test
     */
    public function index_collection_methods()
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, 'true');

        $docs = [
            new Document(['foo' => 'bar']),
            new Document(['baz' => 'john']),
            new Document(['baz' => 'john']),
        ];

        $this->assertNull($docs[0]->_id);

        $index->merge($docs);

        $this->assertNotNull($docs[0]->_id);
    }

    /**
     * @test
     */
    public function index_interfaces()
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, 'true');

        $this->assertInstanceOf(Countable::class, $index);
        $this->assertInstanceOf(ArrayAccess::class, $index);
        $this->assertInstanceOf(IteratorAggregate::class, $index);
    }
}
