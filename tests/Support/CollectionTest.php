<?php

declare(strict_types=1);

namespace Sigmie\Support\Tests;

use PHPUnit\Framework\TestCase;
use Sigmie\Support\Collection;

class CollectionTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function map_with_keys()
    {
        $collection = new Collection(['foo' => ['bar', 'baz']]);

        $self = $this;
        $collection->mapWithKeys(function ($value, $index) use ($self) {
            $self->assertEquals($value, ['bar', 'baz']);
            $self->assertEquals($index, 'foo');

            return [$index, $value];
        });
    }

    /**
     * @test
     */
    public function flatten(): void
    {
        $collection = new Collection([
            ['foo'],
            ['bar', 'baz'],
        ]);

        $flat = $collection->flatten()->toArray();
        $this->assertEquals(['foo', 'bar', 'baz'], $flat);
    }

    /**
     * @test
     */
    public function flatten_with_keys()
    {
        $collection = new Collection([
            ['foo' => 'bar'],
            [
                'john' => 'doe',
                'hi' => ['foo', 'bar'],
            ],
        ]);

        $flat = $collection->flattenWithKeys()->toArray();

        $this->assertEquals([
            'foo' => 'bar',
            'john' => 'doe',
            'hi' => ['foo', 'bar'],
        ], $flat);
    }
}
