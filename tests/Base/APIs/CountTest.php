<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs;

use Sigmie\Base\APIs\Count as CountAPI;
use Sigmie\Base\Documents\Document;
use Sigmie\Testing\TestCase;

class CountTest extends TestCase
{
    use CountAPI;

    /**
     * @test
     */
    public function count_api_call(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, 'true');

        $doc1 = new Document(['foo' => 'bar'], '0');
        $doc2 = new Document(['foo' => 'bar'], '1');
        $doc3 = new Document(['foo' => 'bar'], '2');

        $index->add($doc1);
        $index->add($doc2);
        $index->add($doc3, 'refresh');

        $res = $this->countAPICall($indexName);

        $this->assertEquals(3, $res->json('count'));
    }
}
