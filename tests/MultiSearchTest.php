<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Testing\TestCase;

class MultiSearchTest extends TestCase
{
    /**
     * @test
     */
    public function weighted_query_string()
    {
        $indexName1 = uniqid();
        $indexName2 = uniqid();

        $blueprint = new NewProperties();
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName1)
            ->lowercase()
            ->properties($blueprint)
            ->create();

        $this->sigmie->newIndex($indexName2)
            ->lowercase()
            ->properties($blueprint)
            ->create();

        $collected1 = $this->sigmie->collect($indexName1, refresh: true);
        $collected2 = $this->sigmie->collect($indexName2, refresh: true);

        $collected1->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Donald']),
        ]);

        $collected2->merge([
            new Document(['name' => 'Goofy']),
        ]);

        $multisearch = $this->sigmie->newMultiSearch();

        $multisearch->newSearch($indexName1)
            ->properties($blueprint)
            ->queryString('Mickey');

        $multisearch->newSearch($indexName2)
            ->properties($blueprint)
            ->queryString('Goofy');

        //Calling the query get method should affect the results
        $res = $multisearch->newQuery($indexName1)->matchAll()->get();

        $res = $multisearch->raw($indexName1, $multisearch->newQuery($indexName1)->matchNone()->toRaw());

        [$search1Res, $search2Res, $newQueryRes, $rawRes] = $multisearch->get();

        $this->assertInstanceOf(SigmieSearchResponse::class, $search1Res);
        $this->assertInstanceOf(SigmieSearchResponse::class, $search2Res);
        $this->assertIsArray($newQueryRes);

        $search1Hit = ($search1Res->json('hits.0._source'));
        $search2Hit = ($search2Res->json('hits.0._source'));

        $this->assertEquals('Mickey', $search1Hit['name']);
        $this->assertEquals('Goofy', $search2Hit['name']);
        $this->assertEquals(2, $newQueryRes['hits']['total']['value']);
        $this->assertEquals(0, $rawRes['hits']['total']['value']);
    }
}
