<?php

namespace Sigma\Test\Unit;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use Sigma\Collection;
use Sigma\Index\Index;

class CollectionTest extends TestCase
{
    /**
     * @var Collection
     */
    private $collection;

    public function setUp(): void
    {
        $this->collection = new class ([]) extends Collection
        {
        };
    }

    /**
     * @test
     */
    public function add(): void
    {
        $this->collection->add(new Index());
        $this->collection->add(new Index());

        $this->assertEquals(2, $this->collection->count());
    }

    /**
     * @test
     */
    public function first(): void
    {
        $this->collection->add(new Index('first'));
        $this->collection->add(new Index('second'));

        $identifier = $this->collection->first()->getIdentifier();

        $this->assertEquals('first', $identifier);
    }

    /**
     * @test
     */
    public function last(): void
    {
        $this->collection->add(new Index('first'));
        $this->collection->add(new Index('second'));
        $this->collection->add(new Index('third'));

        $identifier = $this->collection->last()->getIdentifier();

        $this->assertEquals('third', $identifier);
    }

    /**
     * @test
     */
    public function iterable(): void
    {
        $this->assertIsIterable($this->collection);
    }

    /**
     * @test
     */
    public function arrayAccesible(): void
    {
        $this->collection->add(new Index());

        $this->assertArrayHasKey(0, $this->collection);
    }

    /**
     * @test
     */
    public function countable(): void
    {
        $this->collection->add(new Index());
        $this->collection->add(new Index());

        $this->assertEquals(2, count($this->collection));
    }

    /**
     * @test
     */
    public function iterator(): void
    {
        $iterator =  $this->collection->getIterator();

        $this->assertInstanceOf(ArrayIterator::class, $iterator);
    }

    /**
     * @test
     */
    public function offsetGet(): void
    {
        $this->collection->add(new Index());

        $element = $this->collection->offsetGet(0);

        $this->assertInstanceOf(Index::class, $element);
    }

    /**
     * @test
     */
    public function offsetUnset(): void
    {
        $this->collection->add(new Index());

        $this->collection->offsetUnset(0);

        $this->assertEquals(0, $this->collection->count());
    }

    /**
     * @test
     */
    public function offsetSet(): void
    {
        $this->collection[0] = new Index('foo');
        $this->collection[1] = new Index('bar');

        $this->collection->offsetSet(1, new Index('replaced'));

        $this->assertEquals('replaced', $this->collection[1]->getIdentifier());
    }
}
