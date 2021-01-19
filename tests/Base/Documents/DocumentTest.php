<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Documents;

use PHPUnit\Framework\TestCase;
use Sigmie\Base\Contracts\DocumentCollection;
use Sigmie\Base\Documents\Document;

class DocumentTest extends TestCase
{
    /**
     * @test
     */
    public function get_attribute(): void
    {
        $doc = new Document(['foo' => 'bar', 'john' => 'doe']);

        $this->assertEquals('bar', $doc->getAttribute('foo'));
        $this->assertEquals('doe', $doc->getAttribute('john'));
        $this->assertNull($doc->getAttribute('baz'));
        $this->assertInstanceOf(DocumentCollection::class, $doc->newCollection());
        $this->assertNull($doc->getId());

        $docWithId = new Document([], '1');

        $this->assertEquals(1,$docWithId->getId());
    }

    /**
    * @test
    */
    public function set_attribute()
    {
        $doc = new Document(['foo' => 'bar', 'john' => 'doe']);

        $this->assertEquals('bar', $doc->getAttribute('foo'));

        $doc->setAttribute('foo', 'baz');

        $this->assertEquals('baz', $doc->getAttribute('foo'));
    }
}