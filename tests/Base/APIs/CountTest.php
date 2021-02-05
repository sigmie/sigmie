<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs;

use Sigmie\Base\APIs\Calls\Count as CountAPI;
use Sigmie\Base\Documents\Document;
use Sigmie\Testing\TestCase;
use Sigmie\Testing\TestConnection;
use Sigmie\Testing\TestIndex;

class CountTest extends TestCase
{
    use TestConnection, CountAPI, TestIndex;

    /**
     * @test
     */
    public function count_api_call(): void
    {
        $index = $this->getTestIndex();

        $doc1 = new Document(['foo' => 'bar'], '0');
        $doc2 = new Document(['foo' => 'bar'], '1');
        $doc3 = new Document(['foo' => 'bar'], '2');

        $index->addDocument($doc1);
        $index->addDocument($doc2);
        $index->addDocument($doc3);

        $res = $this->countAPICall($index->getName());

        $this->assertEquals(3, $res->json('count'));
    }
}
