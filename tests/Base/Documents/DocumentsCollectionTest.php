<?php

declare(strict_types=1);

namespace Sigmie\Tests\Documents;

use PHPUnit\Framework\TestCase;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Documents\DocumentsCollection;
use TypeError;

class DocumentsCollectionTest extends TestCase
{
    /**
    * @test
    */
    public function add_documents_array()
    {
        $docs = new DocumentsCollection();

         $docs2 = [
            new Document(['baz' => 'first baz value'], '00'),
            new Document(['baz' => 'john'], '99'),
            new Document(['baz' => 'last baz value'], '88'),
        ];

        $docs->addDocuments($docs2);

        $this->assertCount(3,$docs);
    }
    /**
    * @test
    */
    public function add_documents_collection()
    {
        $docs = new DocumentsCollection();

         $docs2 = new DocumentsCollection([
            new Document(['baz' => 'first baz value'], '00'),
            new Document(['baz' => 'john'], '99'),
            new Document(['baz' => 'last baz value'], '88'),
        ]);

        $docs->addDocuments($docs2);

        $this->assertCount(3,$docs);
    }
    /**
    * @test
    */
    public function empty_methods()
    {
        $docs = new DocumentsCollection();

        $this->assertTrue($docs->isEmpty());
        $this->assertFalse($docs->isNotEmpty());
    }

    /**
     * @test
     */
    public function get()
    {
        $docs = new DocumentsCollection([
            new Document(['baz' => 'first baz value'], '00'),
            new Document(['baz' => 'john'], '99'),
            new Document(['baz' => 'last baz value'], '88'),
        ]);

        $doc = $docs->get('99');

        $this->assertInstanceOf(Document::class, $doc);
        $this->assertEquals('john', $doc->getAttribute('baz'));
        $this->assertEquals('99', $doc->getId());

        $doc = $docs->get('100');
        $this->assertNull($doc);
    }

    /**
     * @test
     */
    public function contains_id()
    {
        $docs = new DocumentsCollection([
            new Document(['baz' => 'first baz value'], '00'),
            new Document(['baz' => 'john'], '99'),
            new Document(['baz' => 'last baz value'], '88'),
        ]);

        $this->assertTrue($docs->contains('88'));
        $this->assertFalse($docs->contains('100'));
    }

    /**
     * @test
     */
    public function last_and_first_docs()
    {
        $docs = new DocumentsCollection([
            new Document(['baz' => 'first baz value']),
            new Document(['baz' => 'john']),
            new Document(['baz' => 'last baz value']),
        ]);

        $first = $docs->first();
        $this->assertEquals($first->getAttribute('baz'), 'first baz value');

        $last = $docs->last();
        $this->assertEquals($last->getAttribute('baz'), 'last baz value');
    }

    /**
     * @test
     */
    public function add_single_document()
    {
        $docs = new DocumentsCollection();
        $docs->addDocument(new Document(['foo' => 'bar']));

        $this->assertCount(1, $docs);
    }

    /**
     * @test
     */
    public function collection_clear(): void
    {
        $docs = new DocumentsCollection([
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
    public function docs_collection_accepts_only_docs()
    {
        $this->expectException(TypeError::class);

        new DocumentsCollection([
            new Document(['foo' => 'bar'], '1'),
            '',
            new Document(['baz' => 'john'], '4'),
        ]);
    }

    /**
     * @test
     */
    public function documents_count(): void
    {
        $docs = new DocumentsCollection([
            new Document(['foo' => 'bar']),
            new Document(['baz' => 'john']),
            new Document(['baz' => 'john']),
        ]);

        $this->assertCount(3, $docs);

        $docsWithIds = new DocumentsCollection([
            new Document(['foo' => 'bar'], '1'),
            new Document(['baz' => 'john'], '2'),
            new Document(['baz' => 'john'], '3'),
            new Document(['baz' => 'john'], '4'),
        ]);

        $this->assertCount(4, $docsWithIds);
    }
}
