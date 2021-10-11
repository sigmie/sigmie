<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Documents;

use Sigmie\Base\Documents\Document;
use Sigmie\Testing\TestCase;

class DocumentTest extends TestCase
{
    /**
     * @test
     */
    public function assertions()
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)
            ->withoutMappings()
            ->create();

        $collection = $this->sigmie->collect($indexName, 'true');

        $doc = new Document(['foo' => 'bar', 'john' => 'doe'], 'bar');

        $this->assertDocumentIsMissing($indexName, $doc);

        $collection->add($doc);

        $this->assertDocumentExists($indexName, $doc);

        $this->assertIndexHas($indexName, ['foo' => 'bar']);
        $this->assertIndexMissing($indexName, ['demo' => 'bar']);
    }
    /**
     * @test
     */
    public function get_attribute(): void
    {
        $doc = new Document(['foo' => 'bar', 'john' => 'doe']);

        $this->assertEquals('bar', $doc->foo);
        $this->assertEquals('doe', $doc->john);
        $this->assertNull($doc->baz);
        $this->assertNull($doc->_id);

        $docWithId = new Document([], '1');

        $this->assertEquals(1, $docWithId->_id);
    }

    /**
     * @test
     */
    public function set_attribute()
    {
        $doc = new Document(['foo' => 'bar', 'john' => 'doe']);

        $this->assertEquals('bar', $doc->foo);

        $doc->foo =  'baz';

        $this->assertEquals('baz', $doc->foo);
    }
}
