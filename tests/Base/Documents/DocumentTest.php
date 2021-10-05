<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Documents;

use Sigmie\Base\Contracts\DocumentCollection;
use Sigmie\Base\Documents\Document;
use Sigmie\Testing\TestCase;

class DocumentTest extends TestCase
{
    /**
     * @test
     */
    public function immutable_id()
    {
        $this->expectError();

        $doc = new Document(_id: 'bar');

        $doc->_id = 'demo';
    }

    /**
     * @test
     */
    public function set_id_after_init()
    {
        $doc = new Document();

        $doc->_id = 'demo';

        $this->assertTrue($doc->_id === 'demo');

        $this->expectError();

        $doc->_id = 'bar';
    }

    /**
     * @test
     */
    public function assertions()
    {
        $name = uniqid();
        $index = $this->sigmie->newIndex($name)
            ->withoutMappings()
            ->create()->collect();

        $doc = new Document(['foo' => 'bar', 'john' => 'doe'], 'bar');

        $this->assertDocumentIsMissing($doc);

        $index->add($doc);

        $this->assertDocumentExists($doc);

        $this->assertIndexHas($name, ['foo' => 'bar']);
        $this->assertIndexMissing($name, ['demo' => 'bar']);
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
