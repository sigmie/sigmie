\PHPUnit\Framework\TestCase?php

declare(strict_types=1);

namespace Sigmie\Base\APIs\Calls;

use Sigmie\Base\APIs\Calls\Mget as MgetAPI;
use Sigmie\Base\Contracts\DocumentCollection;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Documents\DocumentsCollection as DocumentsDocumentCollection;
use Sigmie\Testing\TestCase;
use Sigmie\Testing\TestConnection;
use Sigmie\Testing\TestIndex;

class MgetTest extends TestCase
{
    use TestConnection, MgetAPI, TestIndex;

    /**
     * @test
     */
    public function mget_api_call(): void
    {
        $index = $this->getTestIndex();

        $docs = new DocumentsDocumentCollection([
            new Document(id: '1', attributes:['foo' => 'bar']),
            new Document(id: '2', attributes:['foo' => 'baz']),
        ]);

        $index->addDocuments($docs);

        $body = ['docs' => [['_id' => '1'], ['_id' => '2']]];

        $mgetRes = $this->mgetAPICall($body);

        $this->assertInstanceOf(DocumentCollection::class, $mgetRes,'Mget API response should implement DocumentCollection');
        $this->assertCount(2, $mgetRes, 'Mget response should implement be Countable interface');
        $this->assertTrue($mgetRes->contains('1'));
        $this->assertTrue($mgetRes->contains('2'));
    }
}
