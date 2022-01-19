<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs;

use Sigmie\Base\APIs\Delete as DeleteAPI;
use Sigmie\Base\Documents\Document;
use Sigmie\Testing\TestCase;

class DeleteTest extends TestCase
{
    use DeleteAPI;

    /**
     * @test
     */
    public function delete_api_call(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, 'true');

        $doc = new Document(['foo' => 'bar'], '0');

        $index->add($doc, 'true');

        $this->assertCount(1, $index);

        $this->deleteAPICall($indexName, '0', 'true');

        $this->assertCount(0, $index);
    }
}
