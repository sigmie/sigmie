<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs\Responses;

use Sigmie\Base\APIs\Bulk as BulkAPI;
use Sigmie\Base\Http\Responses\Bulk;
use Sigmie\Testing\TestCase;
use Sigmie\Base\Exceptions\BulkException;

class BulkTest extends TestCase
{
    use BulkAPI;

    /**
     * @test
     */
    public function bulk_response(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName,'true');

        $this->expectException(BulkException::class);

        $body = [
            ['create' => ['_id' => 1]],
            ['field_foo' => 'value_bar'],
            ['create' => ['_id' => 2]],
            ['field_foo' => 'value_baz'],
            ['create' => ['_id' => 2]],
            ['field_foo' => 'value_baz'],
            ['update' => ['_id' => 3]],
            ['doc' => ['demo'=>'bar'], 'doc_as_upsert' => true],
        ];


        $bulkRes = $this->bulkAPICall($indexName, $body);

        $this->assertInstanceOf(Bulk::class, $bulkRes, 'Bulk API should return a Bulk response');
        $this->assertCount(2, $bulkRes->getSuccessful(), 'Bulk response getSuccessful method should contains 1 element.');
        $this->assertCount(0, $bulkRes->getFailed(), 'Bulk response getFailed method should contains 1 element.');
    }
}
