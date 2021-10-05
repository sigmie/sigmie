<?php

declare(strict_types=1);

namespace Sigmie\Base\Tests;

use Sigmie\Base\Index\ActiveIndex;
use Sigmie\Base\Index\AliasedIndex;
use Sigmie\Base\Index\Index;
use Sigmie\Base\Index\IndexBlueprint;
use Sigmie\Support\Alias\Actions as IndexActions;
use Sigmie\Support\Contracts\Collection;
use Sigmie\Testing\TestCase;
use Sigmie\Testing\TestConnection;

class ActionsTest extends TestCase
{
    use IndexActions, TestConnection;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function index_exists()
    {
        $indexName = uniqid();

        $index = new AliasedIndex($indexName);

        $exists = $this->indexExists($index);

        $this->assertFalse($exists);

        $this->createIndex($indexName, new IndexBlueprint());

        $exists = $this->indexExists($index);

        $this->assertTrue($exists);
    }

    /**
     * @test
     */
    public function create_index(): void
    {
        $indexName = uniqid();

        $this->createIndex($indexName, new IndexBlueprint());

        $this->assertIndexExists($indexName);
    }

    /**
     * @test
     */
    public function delete_index()
    {
        $indexName = uniqid();

        $this->createIndex($indexName, new IndexBlueprint());

        $this->deleteIndex($indexName);

        $array = $this->listIndices()->map(fn (ActiveIndex $index) => $index->name)->toArray();

        $this->assertNotContains($indexName, $array);
    }

    /**
     * @test
     */
    public function list_indices()
    {
        $fooIndexName = uniqid();
        $barIndexName = uniqid();

        $this->createIndex($fooIndexName, new IndexBlueprint());
        $this->createIndex($barIndexName, new IndexBlueprint());

        $list = $this->listIndices();
        $array = $list->map(fn (ActiveIndex $index) => $index->name)->toArray();

        $this->assertContains($fooIndexName, $array);
        $this->assertContains($barIndexName, $array);

        $this->assertInstanceOf(Collection::class, $list);

        $list->each(fn ($index, $key) => $this->assertInstanceOf(ActiveIndex::class, $index));
    }
}
