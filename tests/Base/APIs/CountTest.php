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

        $index->addDocument(new Document(['foo' => 'bar'], '0'));
        $index->addDocument(new Document(['foo' => 'bar'], '1'));
        $index->addDocument(new Document(['foo' => 'bar'], '2'));

        $res = $this->countAPICall($index->getName());

        $this->assertEquals(3, $res->json('count'));
    }
}
