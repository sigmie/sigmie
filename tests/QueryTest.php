<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Exception;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Mappings;
use Sigmie\Mappings\NewProperties;
use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Document\Document;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Query\Queries\Compound\Boolean as QueriesCompoundBoolean;
use Sigmie\Testing\TestCase;

use function Sigmie\Functions\random_letters;

class QueryTest extends TestCase
{
    /**
     * @test
     */
    public function valid_range_query()
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
    public function valid_search()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->create();

        $res = $this->sigmie->newQuery($name)->bool(function (QueriesCompoundBoolean $boolean) {
            $boolean->filter->matchAll();
            $boolean->filter->matchNone();
            $boolean->filter->fuzzy('bar', 'baz');
            $boolean->filter()->multiMatch(['foo', 'bar'], 'baz');

            $boolean->must->term('foo', 'bar');
            $boolean->must->exists('bar');
            $boolean->must->terms('foo', ['bar', 'baz']);

            $boolean->mustNot->wildcard('foo', '**/*');
            $boolean->mustNot->ids(['unqie']);

            $boolean->should->bool(fn (QueriesCompoundBoolean $boolean) => $boolean->must->match('foo', 'bar'));
        })
            ->from(0)
            ->size(2)
            ->response();

        $this->assertInstanceOf(SearchResponse::class, $res);
    }

    /**
     * @test
     */
    public function query_clauses()
    {
        $query = $this->sigmie->newQuery('')->bool(function (QueriesCompoundBoolean $boolean) {
            $boolean->filter->matchAll();
            $boolean->filter->matchNone();
            $boolean->filter->fuzzy('bar', 'baz');
            $boolean->filter()->multiMatch(['foo', 'bar'], 'baz');

            $boolean->must->term('foo', 'bar');
            $boolean->must->exists('bar');
            $boolean->must->terms('foo', ['bar', 'baz']);

            $boolean->mustNot->wildcard('foo', '**/*');
            $boolean->mustNot->ids(['unqie']);

            $boolean->should->bool(fn (QueriesCompoundBoolean $boolean) => $boolean->must->match('foo', 'bar'));
        })->sort('title.raw', 'asc')
            ->fields(['title'])
            ->from(0)
            ->size(2)
            ->getDSL();

        $this->assertArrayHasKey('_source', $query);
        $this->assertArrayHasKey('query', $query);
        $this->assertArrayHasKey('sort', $query);
        $this->assertArrayHasKey('from', $query);
        $this->assertArrayHasKey('size', $query);

        $this->assertEquals(
            $query['query'],
            ['bool' => [
                'boost' => 1.0,
                'filter' => [
                    ['match_all' => (object) [
                        'boost' => 1.0,
                    ]],
                    ['match_none' => (object) [
                        'boost' => 1.0,
                    ]],
                    ['fuzzy' => ['bar' => ['value' => 'baz']]],
                    ['multi_match' => [
                        'fields' => ['foo', 'bar'],
                        'boost' => 1.0,
                        'query' => 'baz'
                    ]],
                ],
                'must' => [
                    [
                        'term' => [
                            'foo' => [
                                'value' => 'bar',
                                'boost' => 1.0,
                            ],
                        ],
                    ],
                    [
                        'exists' => [
                            'field' => 'bar',
                            'boost' => 1.0,
                        ],
                    ],
                    [
                        'terms' => [
                            'foo' => ['bar', 'baz'],
                            'boost' => 1.0,
                        ],
                    ],
                ],
                'must_not' => [
                    [
                        'wildcard' => [
                            'foo' => [
                                'value' => '**/*',
                                'boost' => '1.0',
                            ],
                        ],
                    ],
                    [
                        'ids' => [
                            'values' => [
                                'unqie',
                            ],
                            'boost' => 1.0,
                        ]
                    ],
                ],
                'should' => [
                    [
                        'bool' => [
                            'must' => [
                                ['match' => [
                                    'foo' => [
                                        'query' => 'bar',
                                        'boost' => 1.0,
                                    ],
                                ]],
                            ],
                            'boost' => 1.0,
                        ],
                    ],
                ],
            ]]
        );
    }
}
