<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs;

use Sigmie\Base\APIs\Bulk;
use Sigmie\Support\Alias\Actions as IndexActions;
use Sigmie\Testing\TestCase;
use Sigmie\Testing\TestConnection;
use Sigmie\Testing\TestIndex;

class BulkTest extends TestCase
{
    use TestConnection, IndexActions, Bulk, TestIndex;

    /**
     * @test
     */
    public function bulk_api_call(): void
    {
        $index = $this->getIndex($this->testIndexName);

        $body = [
            ['create' => ['_id' => 1]],
            ['field_foo' => 'value_bar'],
            ['create' => ['_id' => 2]],
            ['field_foo' => 'value_baz'],
        ];

        $res = $this->bulkAPICall($index->name, $body);

        $this->assertCount(2, $res->json('items'));
    }
}
