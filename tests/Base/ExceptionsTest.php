<?php

declare(strict_types=1);

namespace Sigmie\Base\Tests\Index;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Documents\DocumentCollection;
use Sigmie\Testing\TestCase;
use Sigmie\Base\APIs\Reindex;
use Sigmie\Base\Exceptions\IllegalArgumentException;
use Sigmie\Base\Exceptions\IndexNotFoundException ;
use Sigmie\Base\Exceptions\VersionConflictEngineException ;
use Sigmie\Base\Exceptions\DocumentMissingException ;
use Sigmie\Base\Exceptions\BulkException ;
use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\APIs\Bulk;

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
                ['friend', 'buddy', 'partner']
            ],  name: 'sigmie_two_way_synonyms',)
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
    }
}
