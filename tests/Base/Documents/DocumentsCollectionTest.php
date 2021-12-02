<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Documents;

use PHPUnit\Framework\TestCase;
use Sigmie\Base\Documents\Collection as DocumentCollection;
use Sigmie\Base\Documents\Document;

class DocumentsCollectionTest extends TestCase
{
    /**
     * @test
     */
    public function add_documents_array()
    {
        $docs = new DocumentCollection();

        $docs2 = [
            new Document(['baz' => 'first baz value'], '00'),
            new Document(['baz' => 'john'], '99'),
            new Document(['baz' => 'last baz value'], '88'),
        ];

        $docs->merge($docs2);

        $this->assertCount(3, $docs);
    }
    /**
     * @test
     */
    public function add_documents_collection()
    {
        $docs = new DocumentCollection();

        $docs2 = new DocumentCollection([
            new Document(['baz' => 'first baz value'], '00'),
            new Document(['baz' => 'john'], '99'),
            new Document(['baz' => 'last baz value'], '88'),
        ]);

        $docs->merge($docs2);

        $this->assertCount(3, $docs);
    }
    /**
     * @test
     */
    public function empty_methods()
    {
        $docs = new DocumentCollection();

        $this->assertTrue($docs->isEmpty());
        $this->assertFalse($docs->isNotEmpty());
    }

    /**
     * @test
     */
    public function get()
    {
        $docs = new DocumentCollection([
            new Document(['baz' => 'first baz value'], '00'),
            new Document(['baz' => 'john'], '99'),
            new Document(['baz' => 'last baz value'], '88'),
        ]);

        $doc = $docs->get('1');

        $this->assertEquals('john', $doc->baz);
        $this->assertEquals('99', $doc->_id);

        $doc = $docs->get('100');
        $this->assertNull($doc);
    }


    /**
     * @test
     */
    public function last_and_first_docs()
    {
        $docs = new DocumentCollection([
            new Document(['baz' => 'first baz value']),
            new Document(['baz' => 'john']),
            new Document(['baz' => 'last baz value']),
        ]);

        $first = $docs[0];
        $this->assertEquals($first->baz, 'first baz value');

        $last = $docs[2];
        $this->assertEquals($last->baz, 'last baz value');
    }

    /**
     * @test
     */
    public function add_single_document()
    {
        $docs = new DocumentCollection();
        $doc =  new Document(['foo' => 'bar']);
        $docs->add($doc);

        $this->assertCount(1, $docs);
    }

    /**
     * @test
     */
    public function collection_clear(): void
    {
        $docs = new DocumentCollection([
            new Document(['baz' => 'john']),
            new Document(['baz' => 'john']),
            new Document(['baz' => 'boyaah']),
        ]);

        $this->assertCount(3, $docs);
        $docs->clear();
        $this->assertCount(0, $docs);
    }

    /**
     * @test
     */
    public function documents_count(): void
    {
        $docs = new DocumentCollection([
            new Document(['foo' => 'bar']),
            new Document(['baz' => 'john']),
            new Document(['baz' => 'john']),
        ]);

        $this->assertCount(3, $docs);

        $docsWithIds = new DocumentCollection([
            new Document(['foo' => 'bar'], '1'),
            new Document(['baz' => 'john'], '2'),
            new Document(['baz' => 'john'], '3'),
            new Document(['baz' => 'john'], '4'),
        ]);

        $this->assertCount(4, $docsWithIds);
    }
}
