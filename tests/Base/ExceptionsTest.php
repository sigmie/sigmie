<?php

declare(strict_types=1);

namespace Sigmie\Base\Tests\Index;

use Sigmie\Base\APIs\Bulk;
use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\Exceptions\BulkException ;
use Sigmie\Base\Exceptions\IllegalArgumentException;
use Sigmie\Base\Exceptions\IndexNotFoundException ;
use Sigmie\Testing\TestCase;

class ExceptionsTest extends TestCase
{
    use IndexAPI;
    use Bulk;

    /**
    * @test
    */
    public function illegal_argument()
    {
        $this->expectException(IllegalArgumentException::class);

        $this->sigmie->newIndex(uniqid())
            ->stopwords(['about', 'after', 'again'], 'sigmie_stopwords')
            ->twoWaySynonyms([
                ['about', 'again', 'after', 'price'],
                ['friend', 'buddy', 'partner'],
            ], name: 'sigmie_two_way_synonyms', )
            ->stemming([
                ['about', ['again', 'after']],
            ], 'sigmie_stemmer_overrides')
            ->withoutMappings()
            ->create();
    }

    /**
    * @test
    */
    public function index_not_found()
    {
        $alias = uniqid();

        $this->expectException(IndexNotFoundException::class);

        $res = $this->indexAPICall("/{$alias}", 'GET');
    }

    /**
    * @test
    */
    public function bulk()
    {
        $indexName = uniqid();
        $index = $this->sigmie->collect($indexName, 'true');

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
    }
}
