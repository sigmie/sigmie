<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\BooleanQueryBuilder;
use Sigmie\Query\Queries\Compound\Boolean as QueriesCompoundBoolean;
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
    public function valid_search(): void
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->create();

        $res = $this->sigmie->newQuery($name)->bool(function (QueriesCompoundBoolean $boolean): void {
            $boolean->filter->matchAll();
            $boolean->filter->matchNone();
            $boolean->filter->fuzzy('bar', 'baz');
            $boolean->filter()->multiMatch(['foo', 'bar'], 'baz');

            $boolean->must->term('foo', 'bar');
            $boolean->must->exists('bar');
            $boolean->must->terms('foo', ['bar', 'baz']);

            $boolean->mustNot->wildcard('foo', '**/*');
            $boolean->mustNot->ids(['unqie']);

            $boolean->should->bool(fn (QueriesCompoundBoolean $boolean): BooleanQueryBuilder => $boolean->must->match('foo', 'bar'));
        })
            ->from(0)
            ->size(2)
            ->response();

        $this->assertInstanceOf(SearchResponse::class, $res);
    }

    /**
     * @test
     */
    public function query_clauses(): void
    {
        $query = $this->sigmie->newQuery('')->bool(function (QueriesCompoundBoolean $boolean): void {
            $boolean->filter->matchAll();
            $boolean->filter->matchNone();
            $boolean->filter->fuzzy('bar', 'baz');
            $boolean->filter()->multiMatch(['foo', 'bar'], 'baz');

            $boolean->must->term('foo', 'bar');
            $boolean->must->exists('bar');
            $boolean->must->terms('foo', ['bar', 'baz']);

            $boolean->mustNot->wildcard('foo', '**/*');
            $boolean->mustNot->ids(['unqie']);

            $boolean->should->bool(fn (QueriesCompoundBoolean $boolean): BooleanQueryBuilder => $boolean->must->match('foo', 'bar'));
        })->sort(['title.raw' => 'asc'])
            ->fields(['title'])
            ->from(0)
            ->size(2)
            ->getDSL();

        $this->assertArrayHasKey('_source', $query);
        $this->assertArrayHasKey('query', $query);
        $this->assertArrayHasKey('sort', $query);
        $this->assertArrayHasKey('from', $query);
        $this->assertArrayHasKey('size', $query);

        $expected = [
            'bool' => [
                'must' => [
                    ['term' => ['foo' => ['value' => 'bar', 'boost' => 1.0]]],
                    ['exists' => ['field' => 'bar', 'boost' => 1.0]],
                    ['terms' => ['foo' => ['bar', 'baz'], 'boost' => 1.0]],
                ],
                'must_not' => [
                    ['wildcard' => ['foo' => ['value' => '**/*', 'boost' => 1.0]]],
                    ['ids' => ['values' => ['unqie'], 'boost' => 1.0]],
                ],
                'should' => [
                    [
                        'bool' => [
                            'must' => [
                                ['match' => [
                                    'foo' => [
                                        'query' => 'bar',
                                        'boost' => 1.0,
                                        'analyzer' => 'default',
                                    ],
                                ]],
                            ],
                            'boost' => 1.0,
                        ],
                    ],
                ],
                'filter' => [
                    ['match_all' => (object) ['boost' => 1.0]],
                    ['match_none' => (object) ['boost' => 1.0]],
                    ['fuzzy' => ['bar' => ['value' => 'baz']]],
                    ['multi_match' => [
                        'query' => 'baz',
                        'boost' => 1.0,
                        'analyzer' => 'default',
                        'fields' => ['foo', 'bar'],
                    ]],
                ],
                'boost' => 1.0,
            ],
        ];

        $this->assertEquals($expected, $query['query']);
    }

    /**
     * @test
     */
    public function search_dsl_only_filters_source_when_fields_are_explicit(): void
    {
        $query = $this->sigmie->newQuery('')
            ->matchAll()
            ->getDSL();

        $this->assertArrayNotHasKey('_source', $query);

        $query = $this->sigmie->newQuery('')
            ->matchAll()
            ->fields(['title'])
            ->getDSL();

        $this->assertSame(['title'], $query['_source']);
    }

    /**
     * @test
     */
    public function low_level_search_can_parse_sort_strings_with_query_properties(): void
    {
        $props = new NewProperties;
        $props->text('title')->keyword()->makeSortable();

        $query = $this->sigmie->newQuery('')
            ->properties($props)
            ->matchAll()
            ->sortString('title:desc')
            ->getDSL();

        $this->assertSame([['title.sortable' => 'desc']], $query['sort']);
    }

    /**
     * @test
     */
    public function match_query_analyzer(): void
    {
        $query = $this->sigmie
            ->newQuery('index')
            ->match(
                'foo',
                'bar',
                analyzer: 'custom_analyzer'
            )
            ->getDSL();

        $this->assertEquals('custom_analyzer', $query['query']['match']['foo']['analyzer']);
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

        $this->assertSame(['alpha', 'beta'], $this->idsFromQuery(fn (): \Sigmie\Query\Search => $this->sigmie->newQuery($indexName)->terms('category', ['books'])));
        $this->assertSame(['alpha', 'beta'], $this->idsFromQuery(fn (): \Sigmie\Query\Search => $this->sigmie->newQuery($indexName)->exists('tag')));
        $this->assertSame(['alpha', 'gamma'], $this->idsFromQuery(fn (): \Sigmie\Query\Search => $this->sigmie->newQuery($indexName)->ids(['alpha', 'gamma'])));
        $this->assertSame(['beta'], $this->idsFromQuery(fn (): \Sigmie\Query\Search => $this->sigmie->newQuery($indexName)->wildcard('sku', 'beta-*')));
        $this->assertSame(['gamma'], $this->idsFromQuery(fn (): \Sigmie\Query\Search => $this->sigmie->newQuery($indexName)->regex('sku', 'gamma-00[0-9]')));
        $this->assertSame(['alpha'], $this->idsFromQuery(fn (): \Sigmie\Query\Search => $this->sigmie->newQuery($indexName)->fuzzy('name', 'Laraval')));
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

        $ids = array_map(fn (array $hit): string => $hit['_id'], $response->json('hits.hits'));

        sort($ids);

        return $ids;
    }
}
