<?php

declare(strict_types=1);

namespace Sigmie\Test\Base\APIs;

use Sigmie\Base\APIs\Mget as MgetAPI;
use Sigmie\Base\Documents\Collection as DocumentCollection;
use Sigmie\Base\Documents\Document;
use Sigmie\Testing\TestCase;

class MgetTest extends TestCase
{
    use MgetAPI;

    /**
     * @test
     */
    public function mget_api_call(): void
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, 'true');

        $docs = new DocumentCollection([
            new Document(_id: '1', _source: ['foo' => 'bar']),
            new Document(_id: '2', _source: ['foo' => 'baz']),
        ]);

        $index->merge($docs);

        $body = ['docs' => [['_id' => '1'], ['_id' => '2']]];

        $mgetRes = $this->mgetAPICall($indexName, $body);

        $this->assertInstanceOf(DocumentCollection::class, $mgetRes->docs(), 'Mget API response should implement DocumentCollection');
        $this->assertCount(2, $mgetRes->docs(), 'Mget response should implement be Countable interface');
    }
}
