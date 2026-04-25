<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Generator;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Testing\TestCase;

class MultiSearchTest extends TestCase
{
    /**
     * @test
     */
    public function weighted_query_string(): void
    {
        $indexName1 = uniqid();
        $indexName2 = uniqid();

        $blueprint = new NewProperties;
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

        // Calling the query get method should affect the results
        $multisearch->newQuery($indexName1)->matchAll()->get();

        $multisearch->raw($indexName1, $multisearch->newQuery($indexName1)->matchNone()->toRaw());

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

    /**
     * @test
     */
    public function multi_lazy_sequences_two_new_search_queries(): void
    {
        $indexName1 = uniqid();
        $indexName2 = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName1)->properties($blueprint)->create();
        $this->sigmie->newIndex($indexName2)->properties($blueprint)->create();

        $this->sigmie->collect($indexName1, refresh: true)->merge([
            new Document(['name' => 'Alpha']),
            new Document(['name' => 'Beta']),
        ]);

        $this->sigmie->collect($indexName2, refresh: true)->merge([
            new Document(['name' => 'Gamma']),
        ]);

        $multi = $this->sigmie->newMultiSearch();

        $multi->newSearch($indexName2)
            ->properties($blueprint)
            ->queryString('');

        $multi->newSearch($indexName1)
            ->properties($blueprint)
            ->queryString('');

        $hits = iterator_to_array($multi->lazy());

        $this->assertCount(3, $hits);
    }

    /**
     * @test
     */
    public function multi_lazy_sequences_two_new_query_match_all(): void
    {
        $indexName1 = uniqid();
        $indexName2 = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName1)->properties($blueprint)->create();
        $this->sigmie->newIndex($indexName2)->properties($blueprint)->create();

        $this->sigmie->collect($indexName1, refresh: true)->merge([
            new Document(['name' => 'A']),
            new Document(['name' => 'B']),
        ]);

        $this->sigmie->collect($indexName2, refresh: true)->merge([
            new Document(['name' => 'C']),
        ]);

        $multi = $this->sigmie->newMultiSearch();

        $multi->newQuery($indexName1)->properties($blueprint)->matchAll();
        $multi->newQuery($indexName2)->properties($blueprint)->matchAll();

        $hits = iterator_to_array($multi->lazy());

        $this->assertCount(3, $hits);
    }

    /**
     * @test
     */
    public function multi_lazy_includes_new_query_entries(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'One']),
            new Document(['name' => 'Two']),
        ]);

        $multi = $this->sigmie->newMultiSearch();

        $multi->newQuery($indexName)->properties($blueprint)->matchAll();

        $hits = iterator_to_array($multi->lazy());

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function multi_lazy_returns_generator(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $multi = $this->sigmie->newMultiSearch();

        $multi->newSearch($indexName)->properties($blueprint)->queryString('');

        $this->assertInstanceOf(Generator::class, $multi->lazy());
    }

    /**
     * @test
     */
    public function multi_each_counts_hits(): void
    {
        $indexName1 = uniqid();
        $indexName2 = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName1)->properties($blueprint)->create();
        $this->sigmie->newIndex($indexName2)->properties($blueprint)->create();

        $this->sigmie->collect($indexName1, refresh: true)->merge([new Document(['name' => 'X'])]);
        $this->sigmie->collect($indexName2, refresh: true)->merge([new Document(['name' => 'Y'])]);

        $multi = $this->sigmie->newMultiSearch();

        $multi->newSearch($indexName1)->properties($blueprint)->queryString('X');
        $multi->newSearch($indexName2)->properties($blueprint)->queryString('Y');

        $count = 0;
        $multi->each(function (Hit $hit) use (&$count): void {
            $count++;
        });

        $this->assertSame(2, $count);
    }

    /**
     * @test
     */
    public function multi_lazy_includes_raw_queries(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'Only']),
        ]);

        $multi = $this->sigmie->newMultiSearch();

        $multi->newSearch($indexName)->properties($blueprint)->queryString('Only');

        $multi->raw($indexName, [
            'query' => ['match_all' => (object) []],
        ]);

        $hits = iterator_to_array($multi->lazy());

        $this->assertCount(2, $hits);
    }
}
