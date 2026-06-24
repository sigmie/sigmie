<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\BooleanQueryBuilder;
use Sigmie\Query\NewQuery;
use Sigmie\Query\Queries\Compound\Boolean as QueriesCompoundBoolean;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\MatchPhrase;
use Sigmie\Query\Search;
use Sigmie\Testing\TestCase;

class QueryTest extends TestCase
{
    /**
     * @test
     */
    public function filter_parse_without_mappings_query(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $collection = $this->sigmie->collect($indexName, refresh: true);

        $docs = [
            new Document([
                'name' => 'John Doe',
                'age' => 20,
            ]),
            new Document([
                'name' => 'John Smith',
                'age' => 25,
            ]),
        ];

        $collection->merge($docs);

        $this->expectException(Exception::class);

        $this->sigmie->newQuery($indexName)
            ->parse('name:"John Doe" AND age<21')
            ->get();
    }

    /**
     * @test
     */
    public function filter_parse_query(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->name('name');
        $blueprint->number('age')->integer();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $collection = $this->sigmie->collect($indexName, refresh: true);

        $docs = [
            new Document([
                'name' => 'John Doe',
                'age' => 20,
            ]),
            new Document([
                'name' => 'John Smith',
                'age' => 25,
            ]),
        ];

        $collection->merge($docs);

        $search = $this->sigmie->newQuery($indexName)
            ->properties($blueprint)
            ->parse('name:"John Doe" AND age<21')
            ->get();

        $count = $search->json('hits.total.value');

        $this->assertEquals(1, $count);

        $search = $this->sigmie->newQuery($indexName)
            ->properties($blueprint)
            ->bool(
                fn (QueriesCompoundBoolean $bool) => $bool->must()->parse('name:"John Doe" AND age<21')
            )
            ->get();

        $count = $search->json('hits.total.value');

        $this->assertEquals(1, $count);
    }

    /**
     * @test
     */
    public function zero_query(): void
    {
        $name = uniqid();

        $blueprint = new NewProperties;
        $blueprint->name('name');

        $this->sigmie->newIndex($name)
            ->properties($blueprint)
            ->create();

        $collection = $this->sigmie->collect($name, refresh: true);

        $docs = [
            new Document([
                'name' => 'John Doe',
            ]),
        ];

        $collection->merge($docs);

        $search = $this->sigmie->newSearch($name)
            ->properties($blueprint)
            ->queryString('0');

        $res = $search->get();

        $res->json();

        // this is a test for completion suggester
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function valid_range_query(): void
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->create();

        $collection = $this->sigmie->collect($name, refresh: true);

        $docs = [
            new Document([
                'count' => 5,
            ]),
            new Document([
                'count' => 15,
            ]),
            new Document([
                'count' => 233,
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->newQuery($name)->range('count', ['>=' => 233])
            ->response();

        $this->assertEquals(1, $res->json()['hits']['total']['value']);

        $res = $this->sigmie->newQuery($name)->range('count', ['<=' => 15])
            ->response();

        $this->assertEquals(2, $res->json()['hits']['total']['value']);
    }

    /**
     * @test
     */
    public function boolean_query_builder_clauses_return_expected_elasticsearch_documents(): void
    {
        $name = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('status');
        $blueprint->keyword('sku');
        $blueprint->number('stock')->integer();
        $blueprint->text('title');

        $this->sigmie->newIndex($name)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($name, refresh: true)->merge([
            new Document(['status' => 'published', 'sku' => 'book-001', 'stock' => 5, 'title' => 'Laravel Search'], _id: 'laravel-search'),
            new Document(['status' => 'published', 'sku' => 'book-002', 'stock' => 0, 'title' => 'Elastic Guide'], _id: 'out-of-stock'),
            new Document(['status' => 'draft', 'sku' => 'draft-001', 'stock' => 8, 'title' => 'Draft Search'], _id: 'draft-search'),
            new Document(['status' => 'published', 'sku' => 'video-001', 'stock' => 4, 'title' => 'Search Video'], _id: 'search-video'),
        ]);

        $response = $this->sigmie->newQuery($name)->bool(function (QueriesCompoundBoolean $boolean): void {
            $boolean->filter->term('status', 'published');
            $boolean->filter->range('stock', ['>' => 0]);

            $boolean->must->regex('sku', 'book-00[0-9]');
            $boolean->must->match('title', 'Search');

            $boolean->mustNot->wildcard('sku', 'video-*');
        })
            ->get();

        $this->assertSame(['laravel-search'], $this->idsFromHits($response->json('hits.hits')));
    }

    /**
     * @test
     */
    public function query_clauses_return_expected_elasticsearch_documents(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('foo');
        $blueprint->keyword('bar');
        $blueprint->text('title')->keyword()->makeSortable();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['foo' => 'bar', 'bar' => 'baz', 'title' => 'Charlie'], _id: 'charlie'),
            new Document(['foo' => 'bar', 'bar' => 'baz', 'title' => 'Alice'], _id: 'alice'),
            new Document(['foo' => 'zip', 'bar' => 'baz', 'title' => 'Beta'], _id: 'beta'),
        ]);

        $response = $this->sigmie->newQuery($indexName)->bool(function (QueriesCompoundBoolean $boolean): void {
            $boolean->filter->matchAll();
            $boolean->filter->fuzzy('bar', 'baz');
            $boolean->filter()->multiMatch(['foo', 'bar'], 'baz');

            $boolean->must->term('foo', 'bar');
            $boolean->must->exists('bar');
            $boolean->must->terms('foo', ['bar', 'baz']);

            $boolean->mustNot->wildcard('title', 'Beta');
            $boolean->mustNot->ids(['missing']);

            $boolean->should->bool(fn (QueriesCompoundBoolean $boolean): BooleanQueryBuilder => $boolean->must->match('foo', 'bar'));
        })
            ->sort([['title.sortable' => 'asc']])
            ->fields(['title'])
            ->from(0)
            ->size(2)
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertSame(['alice', 'charlie'], array_map(fn (array $hit): string => $hit['_id'], $hits));
        $this->assertSame([['title' => 'Alice'], ['title' => 'Charlie']], array_map(fn (array $hit): array => $hit['_source'], $hits));
    }

    /**
     * @test
     */
    public function low_level_search_filters_source_only_when_fields_are_explicit(): void
    {
        $indexName = uniqid();

        $this->sigmie->newIndex($indexName)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['title' => 'Visible', 'body' => 'Hidden body'], _id: 'visible'),
        ]);

        $fullHit = $this->sigmie->newQuery($indexName)
            ->matchAll()
            ->get()
            ->json('hits.hits.0._source');

        $sourceFilteredHit = $this->sigmie->newQuery($indexName)
            ->matchAll()
            ->fields(['title'])
            ->get()
            ->json('hits.hits.0._source');

        $this->assertSame(['title' => 'Visible', 'body' => 'Hidden body'], $fullHit);
        $this->assertSame(['title' => 'Visible'], $sourceFilteredHit);
    }

    /**
     * @test
     */
    public function low_level_search_can_parse_sort_strings_and_order_elasticsearch_results(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title')->keyword()->makeSortable();

        $this->sigmie->newIndex($indexName)
            ->properties($props)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['title' => 'Alpha'], _id: 'alpha'),
            new Document(['title' => 'Zulu'], _id: 'zulu'),
        ]);

        $hits = $this->sigmie->newQuery($indexName)
            ->properties($props)
            ->matchAll()
            ->sortString('title:desc')
            ->get()
            ->json('hits.hits');

        $this->assertSame(['zulu', 'alpha'], array_map(fn (array $hit): string => $hit['_id'], $hits));
    }

    /**
     * @test
     */
    public function match_query_analyzer_changes_elasticsearch_results(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['title' => 'Nico Orfanos'], _id: 'nico'),
        ]);

        $defaultAnalyzerResponse = $this->sigmie
            ->newQuery($indexName)
            ->match('title', 'Nico Orfanos')
            ->get();

        $keywordAnalyzerResponse = $this->sigmie
            ->newQuery($indexName)
            ->match(
                'title',
                'Nico Orfanos',
                analyzer: 'keyword'
            )
            ->get();

        $this->assertEquals(1, $defaultAnalyzerResponse->json('hits.total.value'));
        $this->assertEquals(0, $keywordAnalyzerResponse->json('hits.total.value'));
    }

    /**
     * @test
     */
    public function raw_query(): void
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->create();

        $this->sigmie->collect($name, true)->merge([
            new Document([
                'foo' => 'bar',
            ]),
        ]);

        $res = $this->sigmie->rawQuery($name, [
            'query' => [
                'match' => [
                    'foo' => 'bar',
                ],
            ],
        ]);

        $this->assertEquals(1, $res->json()['hits']['total']['value']);
    }

    /**
     * @test
     */
    public function multi_raw_query(): void
    {
        $name = uniqid();
        $name2 = uniqid();

        $this->sigmie->newIndex($name)->create();
        $this->sigmie->newIndex($name2)->create();

        $docs = [
            new Document([
                'foo' => 'bar',
            ]),
        ];

        $this->sigmie->collect($name, true)->merge($docs);
        $this->sigmie->collect($name2, true)->merge($docs);

        $res = $this->sigmie->rawQuery(sprintf('%s,%s', $name, $name2), [
            'query' => [
                'match' => [
                    'foo' => 'bar',
                ],
            ],
        ]);

        $this->assertEquals(2, $res->json()['hits']['total']['value']);
    }

    /**
     * @test
     */
    public function sort_string_parses_and_sorts_results(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('category')->keyword()->makeSortable();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['category' => 'a']),
            new Document(['category' => 'b']),
        ]);

        $res = $this->sigmie->newQuery($indexName)
            ->properties($blueprint)
            ->sortString('category:desc')
            ->matchAll()
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertEquals('b', $hits[0]['_source']['category']);
        $this->assertEquals('a', $hits[1]['_source']['category']);
    }

    /**
     * @test
     */
    public function term_clause_helpers_return_expected_elasticsearch_documents(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('sku');
        $blueprint->keyword('category');
        $blueprint->keyword('tag');
        $blueprint->text('name');
        $blueprint->number('stock')->integer();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['sku' => 'alpha-001', 'category' => 'books', 'tag' => 'featured', 'name' => 'Laravel Guide', 'stock' => 5], _id: 'alpha'),
            new Document(['sku' => 'beta-002', 'category' => 'books', 'tag' => 'sale', 'name' => 'Elastic Search Handbook', 'stock' => 0], _id: 'beta'),
            new Document(['sku' => 'gamma-003', 'category' => 'music', 'name' => 'Synth Album', 'stock' => 8], _id: 'gamma'),
        ]);

        $this->assertSame(['alpha', 'beta'], $this->idsFromQuery(fn (): Search => $this->sigmie->newQuery($indexName)->terms('category', ['books'])));
        $this->assertSame(['alpha', 'beta'], $this->idsFromQuery(fn (): Search => $this->sigmie->newQuery($indexName)->exists('tag')));
        $this->assertSame(['alpha', 'gamma'], $this->idsFromQuery(fn (): Search => $this->sigmie->newQuery($indexName)->ids(['alpha', 'gamma'])));
        $this->assertSame(['beta'], $this->idsFromQuery(fn (): Search => $this->sigmie->newQuery($indexName)->wildcard('sku', 'beta-*')));
        $this->assertSame(['gamma'], $this->idsFromQuery(fn (): Search => $this->sigmie->newQuery($indexName)->regex('sku', 'gamma-00[0-9]')));
        $this->assertSame(['alpha'], $this->idsFromQuery(fn (): Search => $this->sigmie->newQuery($indexName)->fuzzy('name', 'Laraval')));
    }

    /**
     * @test
     */
    public function object_query_and_post_filter_helpers_run_against_elasticsearch(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');
        $blueprint->keyword('status');
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['category' => 'books', 'status' => 'published', 'title' => 'Search Guide'], _id: 'published-book'),
            new Document(['category' => 'books', 'status' => 'draft', 'title' => 'Draft Guide'], _id: 'draft-book'),
            new Document(['category' => 'music', 'status' => 'published', 'title' => 'Music Theory'], _id: 'published-music'),
        ]);

        $queryResponse = $this->sigmie->newQuery($indexName)
            ->query(new Term('status', 'published'))
            ->get();

        $postFilterResponse = $this->sigmie->newQuery($indexName)
            ->postFilter(new Term('category', 'books'))
            ->get();

        $postFilterStringResponse = $this->sigmie->newQuery($indexName)
            ->properties($blueprint)
            ->postFilterString("status:'published'")
            ->get();

        $matchNoneResponse = $this->sigmie->newQuery($indexName)
            ->matchNone()
            ->get();

        $multiMatchResponse = $this->sigmie->newQuery($indexName)
            ->multiMatch(['title'], 'Guide')
            ->get();

        $this->assertSame(['published-book', 'published-music'], $this->idsFromHits($queryResponse->json('hits.hits')));
        $this->assertSame(['draft-book', 'published-book'], $this->idsFromHits($postFilterResponse->json('hits.hits')));
        $this->assertSame(['published-book', 'published-music'], $this->idsFromHits($postFilterStringResponse->json('hits.hits')));
        $this->assertEquals(0, $matchNoneResponse->json('hits.total.value'));
        $this->assertSame(['draft-book', 'published-book'], $this->idsFromHits($multiMatchResponse->json('hits.hits')));
    }

    /**
     * @test
     */
    public function match_phrase_query_returns_exact_phrase_elasticsearch_hit(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->merge([
                new Document(['title' => 'quick brown fox'], _id: 'exact-phrase'),
                new Document(['title' => 'quick agile brown fox'], _id: 'split-phrase'),
            ]);

        $response = $this->sigmie->newQuery($indexName)
            ->query(new MatchPhrase('title', 'quick brown'))
            ->get();

        $this->assertSame(['exact-phrase'], $this->idsFromHits($response->json('hits.hits')));
    }

    /**
     * @test
     */
    public function explicit_index_term_sort_lazy_and_each_helpers_run_against_elasticsearch(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');
        $blueprint->number('rank')->integer();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['category' => 'books', 'rank' => 2], _id: 'second-book'),
            new Document(['category' => 'books', 'rank' => 1], _id: 'first-book'),
            new Document(['category' => 'music', 'rank' => 3], _id: 'music'),
        ]);

        $response = (new NewQuery($this->elasticsearchConnection))
            ->index($indexName)
            ->sort([['rank' => 'asc']])
            ->term('category', 'books')
            ->get();

        $lazyQuery = new NewQuery($this->elasticsearchConnection, $indexName);
        $lazyQuery->sort([['rank' => 'asc']]);
        $lazyQuery->term('category', 'books');

        $lazyHits = iterator_to_array($lazyQuery->chunk(1)->lazy());

        $eachQuery = new NewQuery($this->elasticsearchConnection, $indexName);
        $eachQuery->sort([['rank' => 'asc']]);
        $eachQuery->term('category', 'books');

        $eachHits = [];
        $eachQuery->chunk(1)->each(function (Hit $hit) use (&$eachHits): void {
            $eachHits[] = $hit->_id;
        });

        $this->assertSame(['first-book', 'second-book'], array_map(fn (array $hit): string => $hit['_id'], $response->json('hits.hits')));
        $this->assertSame(['first-book', 'second-book'], array_map(fn (Hit $hit): string => $hit->_id, $lazyHits));
        $this->assertSame(['first-book', 'second-book'], $eachHits);
    }

    /**
     * @test
     */
    public function post_filter_string_filters_hits_after_query_execution(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['category' => 'books', 'name' => 'Laravel Guide'], _id: 'book'),
            new Document(['category' => 'music', 'name' => 'Laravel Soundtrack'], _id: 'music'),
        ]);

        $response = $this->sigmie->newQuery($indexName)
            ->properties($blueprint)
            ->match('name', 'Laravel')
            ->postFilterString("category:'books'")
            ->get();

        $this->assertSame(['book'], array_map(fn (array $hit): string => $hit['_id'], $response->json('hits.hits')));
    }

    protected function idsFromQuery(callable $query): array
    {
        $response = $query()
            ->get();

        return $this->idsFromHits($response->json('hits.hits'));
    }

    protected function idsFromHits(array $hits): array
    {
        $ids = array_map(fn (array $hit): string => $hit['_id'], $hits);

        sort($ids);

        return $ids;
    }
}
