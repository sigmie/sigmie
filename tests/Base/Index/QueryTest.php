<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Base\Search\Queries\Compound\Boolean as QueriesCompoundBoolean;
use Sigmie\Testing\TestCase;

class QueryTest extends TestCase
{
    use Index;
    use Search;
    use Explain;

    /**
     * @test
     */
    public function valid_range_query()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->withoutMappings()->create();

        $collection = $this->sigmie->collect($name);

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

        $res = $this->sigmie->search($name)->range('count', ['>='=> 233])
            ->response();

        $this->assertEquals(1, $res->json()['hits']['total']['value']);

        $res = $this->sigmie->search($name)->range('count', ['<='=> 15])
            ->response();

        $this->assertEquals(2, $res->json()['hits']['total']['value']);
    }

    /**
     * @test
     */
    public function valid_search()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->withoutMappings()->create();

        $res = $this->sigmie->search($name)->bool(function (QueriesCompoundBoolean $boolean) {
            $boolean->filter->matchAll();
            $boolean->filter->matchNone();
            $boolean->filter->fuzzy('bar', 'baz');
            $boolean->filter()->multiMatch('baz', ['foo', 'bar']);

            $boolean->must->term('foo', 'bar');
            $boolean->must->exists('bar', 'baz');
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
        $query = $this->sigmie->search('')->bool(function (QueriesCompoundBoolean $boolean) {
            $boolean->filter->matchAll();
            $boolean->filter->matchNone();
            $boolean->filter->fuzzy('bar', 'baz');
            $boolean->filter()->multiMatch('baz', ['foo', 'bar']);

            $boolean->must->term('foo', 'bar');
            $boolean->must->exists('bar', 'baz');
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
                'filter' => [
                    ['match_all' => (object) []],
                    ['match_none' => (object) []],
                    ['fuzzy' => ['bar' => ['value' => 'baz']]],
                    ['multi_match' => ['fields' => ['foo', 'bar'], 'query' => 'baz']],
                ],
                'must' => [
                    ['term' => ['foo' => ['value' => 'bar']]],
                    ['exists' => ['field' => 'bar']],
                    ['terms' => ['foo' => ['bar', 'baz']]],
                ],
                'must_not' => [
                    ['wildcard' => ['foo' => ['value' => '**/*']]],
                    ['ids' => ['values' => ['unqie']]],
                ],
                'should' => [
                    [
                        'bool' => [
                            'must' => [
                                ['match' => ['foo' => ['query' => 'bar']]],
                            ],
                        ],
                    ],
                ],
            ]]
        );
    }
}
