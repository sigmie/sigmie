<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs\Responses;

use Sigmie\Base\APIs\Bulk as BulkAPI;
use Sigmie\Base\Http\Responses\Bulk;
use Sigmie\Testing\TestCase;
use Sigmie\Testing\TestIndex;

class BulkTest extends TestCase
{
    use BulkAPI, TestIndex;

    /**
     * @test
     */
    public function bulk_response(): void
    {
        $index = $this->getTestIndex();

        $body = [
            ['create' => ['_id' => 1]],
            ['field_foo' => 'value_bar'],
            ['create' => ['_id' => 2]],
            ['field_foo' => 'value_baz'],
        ];

        $bulkRes = $this->bulkAPICall($index->name(), $body);

        $this->assertInstanceOf(Bulk::class, $bulkRes, 'Bulk API should return a Bulk response');
        $this->assertCount(2, $bulkRes->getSuccessful(), 'Bulk response getSuccessful method should contains 1 element.');
        $this->assertCount(0, $bulkRes->getFailed(), 'Bulk response getFailed method should contains 1 element.');
    }
}
