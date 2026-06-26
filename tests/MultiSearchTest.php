<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Generator;
use Sigmie\Base\APIs\MSearch;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\Formatters\FormatterParameters;
use Sigmie\Search\Formatters\SigmieMultiSearchResponse;
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
    public function legacy_multisearch_formatter_formats_elasticsearch_responses(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)
            ->lowercase()
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Donald']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Mickey')
            ->size(1);

        $query = $this->sigmie->newQuery($indexName);
        $query->matchAll();

        $rawResponse = $this->rawMultiSearch([
            ...$search->toMultiSearch(),
            ...$query->toMultiSearch(),
        ]);

        $response = (new SigmieMultiSearchResponse)
            ->multiSearchResponseRaw($rawResponse)
            ->searches([$search, $query]);

        $formatted = $response->format();

        $this->assertSame('Mickey', $formatted[0]['hits'][0]['_source']['name']);
        $this->assertSame(2, $formatted[1]['total']);
        $this->assertSame($formatted, $response->json());
        $this->assertSame('Mickey', $response->json('0.hits.0._source')['name']);
        $this->assertSame($formatted[0], $response->getSearchResult(0));
        $this->assertNull($response->getSearchResult(2));
        $this->assertSame($formatted, $response->getAllResults());

        $formattedQueryResponse = $query->formatMultiSearchResponse($rawResponse['responses'], 1);
        $slicedQueryResponse = $query->sliceMultiSearchResponse([$rawResponse['responses'][1]]);

        $this->assertSame(1, $query->multisearchResCount());
        $this->assertSame(2, $formattedQueryResponse['total']);
        $this->assertSame(2, $slicedQueryResponse['total']);
        $this->assertFalse($formattedQueryResponse['timed_out']);
        $this->assertCount(2, $slicedQueryResponse['hits']);
    }

    /**
     * @test
     */
    public function formatter_parameters_drive_an_elasticsearch_search(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->category('type');
        $blueprint->number('rank');

        $this->sigmie->newIndex($indexName)
            ->lowercase()
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'Alpha Guide', 'type' => 'docs', 'rank' => 1]),
            new Document(['name' => 'Beta Guide', 'type' => 'docs', 'rank' => 2]),
            new Document(['name' => 'Beta Note', 'type' => 'notes', 'rank' => 3]),
        ]);

        $params = (new FormatterParameters)
            ->queryStrings(['Beta'])
            ->filters("type:'docs'")
            ->sort('rank:desc')
            ->facets('type')
            ->pagination(1, 0)
            ->meta(['scenario' => 'formatter-parameters']);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString($params->getQueryStrings()[0])
            ->filters($params->getFilterString())
            ->sort($params->getSortString())
            ->facets($params->getFacetString())
            ->size($params->getSize())
            ->from($params->getFrom())
            ->get();

        $this->assertSame(1, $response->total());
        $this->assertSame('Beta Guide', $response->hits()[0]->_source['name']);
        $this->assertSame(1, $response->format()['facets']->type->docs);
        $this->assertSame('formatter-parameters', $params->getMeta()['scenario']);
        $this->assertSame($params->getMeta(), $params->toArray()['meta']);
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
    public function grouped_and_flattened_hits_keep_expected_elasticsearch_results(): void
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

        $multi->newSearch($indexName1, 'letters')
            ->properties($blueprint)
            ->queryString('Alpha');

        $multi->raw($indexName2, [
            'query' => [
                'match' => [
                    'name' => 'Gamma',
                ],
            ],
        ], 'raw_letters');

        $grouped = $multi->groupedHits();
        $flattened = $multi->hits();

        $this->assertEquals(200, $multi->responseCode());
        $this->assertEquals('Alpha', $grouped['letters'][0]->_source['name'] ?? null);
        $this->assertEquals('Gamma', $grouped['raw_letters'][0]['_source']['name'] ?? null);
        $this->assertEquals('Alpha', $flattened[0]->_source['name'] ?? null);
        $this->assertEquals('Gamma', $flattened[1]['_source']['name'] ?? null);
    }

    /**
     * @test
     */
    public function raw_multi_search_responses_keep_names(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'Alpha']),
            new Document(['name' => 'Beta']),
        ]);

        $multi = $this->sigmie->newMultiSearch();
        $multi->newSearch($indexName, 'named_search')
            ->properties($blueprint)
            ->queryString('Alpha');

        $multi->raw($indexName, [
            'query' => ['match' => ['name' => 'Beta']],
            'size' => 1,
        ], 'named_raw');

        $namedResponses = $multi->getRawResponsesByName();

        $this->assertArrayHasKey('named_search', $namedResponses);
        $this->assertArrayHasKey('named_raw', $namedResponses);
        $this->assertSame('Alpha', $namedResponses['named_search']['hits']['hits'][0]['_source']['name'] ?? null);
        $this->assertSame('Beta', $namedResponses['named_raw']['hits']['hits'][0]['_source']['name'] ?? null);
    }

    /**
     * @test
     */
    public function add_methods_include_existing_searches_in_elasticsearch_multi_search(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'Alpha']),
            new Document(['name' => 'Beta']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Alpha');

        $query = $this->sigmie->newQuery($indexName)
            ->properties($blueprint);

        $query->match('name', 'Beta');

        $multi = $this->sigmie->newMultiSearch();

        $multi->add($search, 'existing_search');
        $multi->addQuery($query, 'existing_query');

        $grouped = $multi->groupedHits();

        $this->assertEquals('Alpha', $grouped['existing_search'][0]->_source['name'] ?? null);
        $this->assertEquals('Beta', $grouped['existing_query'][0]['_source']['name'] ?? null);
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

    /**
     * @test
     */
    public function multi_lazy_includes_raw_queries_with_sort(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name')->keyword()->makeSortable();

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'Charlie']),
            new Document(['name' => 'Alice']),
            new Document(['name' => 'Bob']),
        ]);

        $multi = $this->sigmie->newMultiSearch();

        $multi->raw($indexName, [
            'query' => ['match_all' => (object) []],
            'sort' => [['name.sortable' => 'asc']],
        ]);

        $hits = iterator_to_array($multi->lazy());
        $names = array_map(fn (Hit $hit): string => (string) $hit['name'], $hits);

        $this->assertSame(['Alice', 'Bob', 'Charlie'], $names);
    }

    /**
     * @test
     */
    public function lazy_new_query_respects_user_sort(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name')->keyword()->makeSortable();

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'a']),
            new Document(['name' => 'b']),
            new Document(['name' => 'c']),
        ]);

        $multi = $this->sigmie->newMultiSearch();

        $multi->newQuery($indexName)
            ->properties($blueprint)
            ->sort([['name.sortable' => 'desc']])
            ->matchAll();

        $hits = iterator_to_array($multi->lazy());
        $names = array_map(fn (Hit $hit): string => (string) $hit['name'], $hits);

        $this->assertSame(['c', 'b', 'a'], $names);
    }

    protected function rawMultiSearch(array $body): array
    {
        $runner = new class($this->elasticsearchConnection)
        {
            use MSearch;

            public function __construct(protected ElasticsearchConnection $elasticsearchConnection) {}

            public function run(array $body): array
            {
                return $this->msearchAPICall($body)->json();
            }
        };

        return $runner->run($body);
    }
}
