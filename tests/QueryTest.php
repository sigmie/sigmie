<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\BooleanQueryBuilder;
use Sigmie\Query\Queries\Compound\Boolean as QueriesCompoundBoolean;
use Sigmie\Query\Queries\ElasticsearchKnn;
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
    public function knn_query(): void
    {
        $name = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->vector('title_embedding', dims: 384);

        $this->sigmie->newIndex($name)
            ->properties($blueprint)
            ->create();

        $collection = $this->sigmie->collect($name, refresh: true);

        $docs = [
            new Document([
                'title' => 'Machine Learning Basics',
                'title_embedding' => array_fill(0, 384, 0.1),
            ]),
            new Document([
                'title' => 'Deep Learning Advanced',
                'title_embedding' => array_fill(0, 384, 0.2),
            ]),
        ];

        $collection->merge($docs);

        $queryVector = array_fill(0, 384, 0.15);

        $dsl = $this->sigmie->newQuery($name)
            ->properties($blueprint)
            ->knn([
                new ElasticsearchKnn(
                    field: 'title_embedding',
                    queryVector: $queryVector,
                    k: 10,
                    numCandidates: 100,
                    filter: [],
                    boost: 1.0
                ),
            ])
            ->getDSL();

        $this->assertArrayHasKey('knn', $dsl);
        $this->assertCount(1, $dsl['knn']);
        $this->assertEquals('title_embedding', $dsl['knn'][0]['field']);
        $this->assertEquals($queryVector, $dsl['knn'][0]['query_vector']);
        $this->assertEquals(10, $dsl['knn'][0]['k']);
        $this->assertEquals(100, $dsl['knn'][0]['num_candidates']);

        $res = $this->sigmie->newQuery($name)
            ->properties($blueprint)
            ->knn([
                new ElasticsearchKnn(
                    field: 'title_embedding',
                    queryVector: $queryVector,
                    k: 10,
                    numCandidates: 100,
                    filter: [],
                    boost: 1.0
                ),
            ])
            ->get();

        $this->assertEquals(2, $res->json()['hits']['total']['value']);
    }

    /**
     * @test
     */
    public function raw_array_query(): void
    {
        $name = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->vector('fixture_vector', dims: 384);

        $this->sigmie->newIndex($name)
            ->properties($blueprint)
            ->create();

        $collection = $this->sigmie->collect($name, refresh: true);

        $vector = array_fill(0, 384, 0.5);

        $docs = [
            new Document([
                'title' => 'Test Document',
                'fixture_vector' => $vector,
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->newQuery($name)
            ->query([
                'script_score' => [
                    'query' => [
                        'match_all' => (object) [],
                    ],
                    'script' => [
                        'source' => "cosineSimilarity(params.query_vector, 'fixture_vector') + 1.0",
                        'params' => [
                            'query_vector' => $vector,
                        ],
                    ],
                ],
            ])
            ->get();

        $this->assertEquals(1, $res->json()['hits']['total']['value']);
    }
}
