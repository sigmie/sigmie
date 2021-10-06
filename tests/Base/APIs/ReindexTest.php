<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs;

use Sigmie\Base\APIs\Bulk;
use Sigmie\Base\APIs\Index as APIsIndex;
use Sigmie\Base\APIs\Reindex;
use Sigmie\Base\Exceptions\ReindexException;
use Sigmie\Base\Index\Index;
use Sigmie\Base\Actions\Alias as AliasActions;
use Sigmie\Testing\TestCase;
use Sigmie\Testing\TestConnection;

class ReindexTest extends TestCase
{
    use AliasActions, Bulk, Reindex, APIsIndex;

    /**
     * @test
     */
    public function reindex_api_call(): void
    {
        $newName = uniqid();
        $oldName = uniqid();

        $oldIndex = $this->sigmie->newIndex($oldName)->withoutMappings()->create();
        $newIndex = $this->sigmie->newIndex($newName)->withoutMappings()->create();

        $body = [
            ['create' => ['_id' => 1]],
            ['field_foo' => 'value_bar'],
            ['create' => ['_id' => 2]],
            ['field_foo' => 'value_baz'],
        ];

        $this->bulkAPICall($oldIndex->name, $body, 'true');

        $collection = $this->sigmie->collect($newName);
        $this->assertCount(0, $collection);
        $this->reindexAPICall($oldIndex->name, $newIndex->name);

        $collection = $this->sigmie->collect($newName);
        $this->assertCount(2, $collection);
    }

    /**
     * @test
     */
    public function reindex_exception(): void
    {
        $newName = uniqid();
        $oldName = uniqid();

        $oldIndex = $this->sigmie->newIndex($oldName)->withoutMappings()->create();
        $newIndex = $this->sigmie->newIndex($newName)->withoutMappings()->create();

        $body = [
            ['create' => ['_id' => 1]],
            ['field_foo' => 'value_bar']
        ];

        //Add data to be reindexed to cause an error
        $this->bulkAPICall($oldIndex->name, $body, 'true');

        //Disable write
        $this->indexAPICall("/{$newIndex->name}/_settings", 'PUT', [
            'index' => ['blocks.write' => true]
        ]);

        $this->expectException(ReindexException::class);

        $this->reindexAPICall($oldIndex->name, $newIndex->name);
    }
}
