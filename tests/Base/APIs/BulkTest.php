<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs;

use Sigmie\Base\APIs\Bulk;
use Sigmie\Testing\TestCase;

class BulkTest extends TestCase
{
    use Bulk;

    /**
     * @test
     */
    public function bulk_api_call(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName,'true');

        $body = [
            ['create' => ['_id' => 1]],
            ['field_foo' => 'value_bar'],
            ['create' => ['_id' => 2]],
            ['field_foo' => 'value_baz'],
        ];

        $res = $this->bulkAPICall($indexName, $body,'true');

        $this->assertCount(2, $res->json('items'));
    }
}
