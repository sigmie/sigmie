<?php

declare(strict_types=1);

namespace Sigmie\Base\Tests;

use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\Index;
use Sigmie\Support\Contracts\Collection;
use Sigmie\Testing\ClearIndices;
use Sigmie\Testing\TestCase;
use Sigmie\Testing\TestConnection;

class ActionsTest extends TestCase
{
    use ClearIndices, IndexActions, TestConnection;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function create_index(): void
    {
        $this->createIndex(new Index('bar'));

        $this->assertIndexExists('bar');
    }

    /**
     * @test
     */
    public function delete_index()
    {
        $this->createIndex(new Index('bar'));

        $this->deleteIndex('bar');

        $this->assertCount(0, $this->listIndices());
    }

    /**
     * @test
     */
    public function list_indices()
    {
        $this->createIndex(new Index('foo'));
        $this->createIndex(new Index('bar'));

        $list = $this->listIndices();

        $this->assertCount(2, $list);
        $this->assertInstanceOf(Collection::class, $list);

        $list->forAll(fn ($key, $index) => $this->assertInstanceOf(Index::class, $index));
    }
}
