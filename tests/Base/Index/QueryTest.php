<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Base\Mappings\Blueprint;
use Sigmie\Base\Search\Clauses\Boolean;
use Sigmie\Base\Search\Compound\Boolean as CompoundBoolean;
use Sigmie\Base\Search\Queries\Compound\Boolean as QueriesCompoundBoolean;
use Sigmie\Base\Search\QueryBuilder;
use Sigmie\Testing\TestCase;

class QueryTest extends TestCase
{
    use Index, Search, Explain;

    private string $alias;

    public function foo(): void
    {
        $this->alias = uniqid();

        $this->sigmie->newIndex($this->alias)
            ->mapping(function (Blueprint $blueprint) {

                $blueprint->text('title', keyword: true)->unstructuredText();
                $blueprint->text('description')->unstructuredText();
                $blueprint->date('created_at')->format('yyyy-MM-dd');
                $blueprint->bool('is_valid');
                $blueprint->number('count')->integer();
                $blueprint->number('avg')->float();
            })
            ->create();

        $collection = $this->sigmie->collect($this->alias);

        $docs = [
            new Document([
                'title' => 'The story of Nemo',
                'description' => 'The father of Nemo began his journey of finding his son.',
                'created_at' => '1994-05-09',
                'is_valid' => true,
                'count' => 5,
                'avg' => 73.3,
            ], '1'),
            new Document([
                'title' => 'Peter Pan and Captain Hook',
                'description' => 'And after this Peter pan woke up in his room.',
                'created_at' => '1995-07-26',
                'is_valid' => false,
                'count' => 233,
                'avg' => 120.3,
            ], '2'),
        ];

        $collection->merge($docs);
    }

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

        $res = $this->sigmie->search($name)->range('count', 233)
            ->get();

        $this->assertEquals(1, $res->json()['hits']['total']['value']);

        $res = $this->sigmie->search($name)->range('count', max: 15)
            ->get();

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
            $boolean->filter()->multiMatch(['foo', 'bar'], 'baz');

            $boolean->must->term('foo', 'bar');
            $boolean->must->exists('bar', 'baz');
            $boolean->must->terms('foo', ['bar', 'baz']);

            $boolean->mustNot->wildcard('foo', '**/*');
            $boolean->mustNot->ids(['unqie']);

            $boolean->should->bool(fn (QueriesCompoundBoolean $boolean) => $boolean->must->match('foo', 'bar'));
        })
            ->from(0)
            ->size(2)
            ->get();

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
            $boolean->filter()->multiMatch(['foo', 'bar'], 'baz');

            $boolean->must->term('foo', 'bar');
            $boolean->must->exists('bar', 'baz');
            $boolean->must->terms('foo', ['bar', 'baz']);

            $boolean->mustNot->wildcard('foo', '**/*');
            $boolean->mustNot->ids(['unqie']);

            $boolean->should->bool(fn (QueriesCompoundBoolean $boolean) => $boolean->must->match('foo', 'bar'));
        })->sortAsc('title.raw')
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
                    ["fuzzy" => ['bar' => ["value" => 'baz',]]],
                    ["multi_match" => ['fields' => ["foo", 'bar',], 'query' => 'baz']]
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
                                ['match' => ['foo' => ['query' => 'bar']]]
                            ]
                        ]
                    ]
                ],
            ]]
        );
    }
}
