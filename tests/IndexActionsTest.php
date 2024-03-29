<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Index;
use Sigmie\Index\Mappings;
use Sigmie\Index\Settings;
use Sigmie\Shared\Collection;
use Sigmie\Testing\TestCase;

class IndexActionsTest extends TestCase
{
    /**
     * @test
     */
    public function get_index()
    {
        $indexName = uniqid();

        $index = $this->sigmie->newIndex($indexName)->create();

        $exists = $this->indexExists($indexName);

        $this->assertTrue($exists);

        $aliasedIndex = $this->getIndex($indexName);

        $this->assertEquals(AliasedIndex::class, $aliasedIndex::class);

        $baseIndex = $this->getIndex($index->name);

        $this->assertEquals(Index::class, $baseIndex::class);
    }

    /**
     * @test
     */
    public function index_exists()
    {
        $indexName = uniqid();

        $index = new AliasedIndex($indexName, uniqid());

        $exists = $this->indexExists($indexName);

        $this->assertFalse($exists);

        $this->createIndex($indexName, $index->settings, $index->mappings);

        $exists = $this->indexExists($indexName);

        $this->assertTrue($exists);
    }

    /**
     * @test
     */
    public function create_index(): void
    {
        $indexName = uniqid();

        $index = new Index($indexName);

        $this->createIndex($indexName, $index->settings, $index->mappings);

        $this->assertIndexExists($indexName);
    }

    /**
     * @test
     */
    public function delete_index()
    {
        $indexName = uniqid();

        $index = new Index($indexName);

        $this->createIndex($indexName, $index->settings, $index->mappings);

        $this->deleteIndex($indexName);

        $indices = $this->listIndices();

        $collection = new Collection($indices);

        $array = $collection->map(fn (Index $index) => $index->name)->toArray();

        $this->assertNotContains($indexName, $array);
    }

    /**
     * @test
     */
    public function list_indices()
    {
        $fooIndexName = uniqid();
        $barIndexName = uniqid();

        $this->createIndex($fooIndexName, new Settings(), new Mappings());
        $this->createIndex($barIndexName, new Settings(), new Mappings());

        $list = new Collection($this->listIndices());
        $array = $list->map(fn (Index $index) => $index->name)->toArray();

        $this->assertContains($fooIndexName, $array);
        $this->assertContains($barIndexName, $array);

        $this->assertInstanceOf(Collection::class, $list);

        $list->each(fn ($index, $key) => $this->assertInstanceOf(Index::class, $index));
    }
}
