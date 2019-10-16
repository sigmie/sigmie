<?php

namespace Sigma\Test\Unit;

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
}
