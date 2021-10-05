<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs;

use Sigmie\Base\APIs\Delete as DeleteAPI;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Index\Index;
use Sigmie\Testing\TestCase;
use Sigmie\Testing\TestConnection;

class DeleteTest extends TestCase
{
    use DeleteAPI;

    /**
     * @test
     */
    public function delete_api_call(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->newIndex($indexName)->withoutMappings()->create()->collect();

        $doc = new Document(['foo' => 'bar'], '0');

        $index->add($doc, 'true');

        $this->assertCount(1, $index);

        $this->deleteAPICall($indexName, '0', 'true');

        $this->assertCount(0, $index);
    }
}
