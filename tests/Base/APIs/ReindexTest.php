<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs;

use Sigmie\Base\APIs\Bulk;
use Sigmie\Base\APIs\Index as APIsIndex;
use Sigmie\Base\APIs\Reindex;
use Sigmie\Base\Exceptions\ReindexException;
use Sigmie\Base\Index\Index;
use Sigmie\Support\Alias\Actions as IndexActions;
use Sigmie\Testing\TestCase;
use Sigmie\Testing\TestConnection;
use Sigmie\Testing\TestIndex;

class ReindexTest extends TestCase
{
    use TestConnection, IndexActions, Bulk, Reindex, TestIndex, APIsIndex;

    /**
     * @test
     */
    public function reindex_api_call(): void
    {
        $newName = uniqid();
        $oldName= uniqid();

        $oldIndex = $this->createIndex(new Index($oldName));

        $newIndex = $this->createIndex(new Index($newName));

        $body = [
            ['create' => ['_id' => 1]],
            ['field_foo' => 'value_bar'],
            ['create' => ['_id' => 2]],
            ['field_foo' => 'value_baz'],
        ];

        $this->bulkAPICall($oldIndex->name(), $body);

        $this->assertCount(0, $newIndex);
        $this->reindexAPICall($oldIndex->name(), $newIndex->name());

        $this->assertCount(2, $newIndex);
    }

    /**
     * @test
     */
    public function reindex_exception(): void
    {
        $name = uniqid();

        $oldIndex = $this->getIndex($this->testIndexName);

        $newIndex = $this->createIndex(new Index($name));

        $body = [
            ['create' => ['_id' => 1]],
            ['field_foo' => 'value_bar']
        ];

        //Add data to be reindexed to cause an error
        $this->bulkAPICall($oldIndex->name(), $body);

        //Disable write
        $this->indexAPICall("/{$newIndex->name()}/_settings", 'PUT', [
            'index' => ['blocks.write' => true]
        ]);

        $this->expectException(ReindexException::class);

        $this->reindexAPICall($oldIndex->name(), $newIndex->name());
    }
}
