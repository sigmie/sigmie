<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Generator;
use Http\Promise\Promise;
use InvalidArgumentException;
use RuntimeException;
use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse as ElasticsearchResponseContract;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Document\RerankedHit;
use Sigmie\Languages\English\English;
use Sigmie\Languages\German\German;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\NewSemanticField;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Parse\InputParser;
use Sigmie\Parse\ParseException;
use Sigmie\Query\Aggregations\Metrics\Composite;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Search\Formatters\RawElasticsearchFormat;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Search\InnerHits;
use Sigmie\Search\MMR;
use Sigmie\Search\NewRerank;
use Sigmie\Search\NewSearch;
use Sigmie\Search\RRF;
use Sigmie\Search\SearchContext;
use Sigmie\Search\VectorPool;
use Sigmie\Shared\Collection;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;

class SearchTest extends TestCase
{
    public function test_paginated_vector_search_keeps_scores_from_every_query_vector(): void
    {
        $name = uniqid('.multi_vector_cursor_');
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 128);
        $field = $props->get()->get('title')->vectorFields()->first()->name;
        $vector = fn (float $x, float $y): array => [$x, $y, ...array_fill(0, 126, 0.0)];
        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        try {
            $this->sigmie->collect($name, refresh: true)->populateEmbeddings(false)->merge([
                new Document(['title' => 'North', '_embeddings' => ['title' => [$field => $vector(1, 0)]]], _id: 'north'),
                new Document(['title' => 'East', '_embeddings' => ['title' => [$field => $vector(-0.1, 1)]]], _id: 'east'),
                new Document(['title' => 'Central', '_embeddings' => ['title' => [$field => $vector(0.8, 0.6)]]], _id: 'central'),
            ]);
            $search = $this->sigmie->newSearch($name)->properties($props)->semantic()->disableKeywordSearch()
                ->queryString('', vector: $vector(1, 0))
                ->queryString('', vector: $vector(0, 1));

            $expected = $search->size(3)->hits();
            $this->assertSame(['central', 'north', 'east'], array_column($expected, '_id'));
            foreach ($expected as $offset => $hit) {
                $page = $search->from($offset)->size(1)->hits();
                $this->assertSame([$hit->_id], array_column($page, '_id'));
                $this->assertSame($hit->_score, $page[0]->_score);
            }
            $this->assertSame([], $search->from(3)->hits());
        } finally {
            $index->delete();
        }
    }

    public function test_lazy_search_omits_highlights_inside_collapsed_groups(): void
    {
        $name = uniqid('.lazy_collapsed_highlight_');
        $props = new NewProperties;
        $props->text('title');
        $props->keyword('edition');
        $props->integer('year');
        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        try {
            $this->sigmie->collect($name, refresh: true)->merge([
                new Document(['title' => 'Search guide', 'edition' => 'guide', 'year' => 2026], _id: 'latest'),
                new Document(['title' => 'Search guide', 'edition' => 'guide', 'year' => 2025], _id: 'older'),
            ]);
            $search = $this->sigmie->newSearch($name)->properties($props)->queryString('Search')
                ->uniqueBy('edition', top: 1)->sort('year:desc')->highlighting(['title'], '<mark>', '</mark>');

            $groupHits = fn (Hit $hit): array => $hit->toArray()['inner_hits']['top']['hits']['hits'];
            $this->assertStringContainsString('<mark>Search</mark>', $groupHits($search->hits()[0])[0]['highlight']['title'][0]);
            $inner = $groupHits($search->lazy()->current());
            $this->assertSame(['older'], array_column($inner, '_id'));
            $this->assertArrayNotHasKey('highlight', $inner[0]);

            $raw = $search->makeSearch()->toRaw();
            $raw['collapse']['inner_hits'] = [$raw['collapse']['inner_hits']];
            $multi = $this->sigmie->newMultiSearch();
            $multi->raw($name, $raw);
            $this->assertSame($inner, $groupHits($multi->lazy()->current()));
        } finally {
            $index->delete();
        }
    }

    public function test_blank_text_inputs_do_not_add_unmatched_hybrid_hits(): void
    {
        $index = uniqid('hybrid_blank_');
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 128);

        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document(['title' => 'guide'], _id: 'guide'),
            // Without a title the document has no embedding, so only a match-all fallback can return it.
            new Document([], _id: 'other'),
        ]);

        $vector = $this->embeddingApi->embed('guide', 128);
        $result = $this->sigmie->newSearch($index)->properties($props)->semantic()
            ->queryString('guide', vector: $vector)->queryString(' ')->size(1)->get();

        $this->assertSame(1, $result->total());
        $this->assertSame(['guide'], array_column($result->hits(), '_id'));
    }

    public function test_image_only_search_returns_only_vector_matches(): void
    {
        $index = uniqid('hybrid_image_');
        $props = new NewProperties;
        $props->text('title');
        $props->image('photo')->semantic(api: 'test-clip', dimensions: 128);
        $vector = [1.0, ...array_fill(0, 127, 0.0)];
        $field = $props->get()->get('photo')->vectorFields()->first()->name;

        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document(['title' => 'with photo', '_embeddings' => ['photo' => [$field => $vector]]], _id: 'photo'),
            new Document(['title' => 'without photo'], _id: 'other'),
        ]);

        $result = $this->sigmie->newSearch($index)->properties($props)->semantic()
            ->queryImage('stored image', vector: $vector, fields: ['photo'])->get();

        $this->assertSame(['photo'], array_column($result->hits(), '_id'));
    }

    public function test_document_boost_scales_the_combined_hybrid_score(): void
    {
        $index = uniqid('hybrid_boost_');
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 128);
        $props->boost('priority');

        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document(['title' => 'guide', 'priority' => 1], _id: 'low'),
            new Document(['title' => 'guide', 'priority' => 5], _id: 'high'),
        ]);

        $result = $this->sigmie->newSearch($index)->properties($props)->semantic()
            ->queryString('guide')->get();
        [$high, $low] = $result->hits();

        $this->assertSame(['high', 'low'], [$high->_id, $low->_id]);
        $this->assertEqualsWithDelta(5 * $low->_score, $high->_score, 0.00001);
    }

    public function test_collapsed_hybrid_inner_hits_keep_the_minimum_score(): void
    {
        $index = uniqid('hybrid_collapse_');
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 128);
        $props->keyword('group');
        $props->integer('order');
        $vector = [1.0, ...array_fill(0, 127, 0.0)];
        $opposite = [-1.0, ...array_fill(0, 127, 0.0)];
        $field = $props->get()->get('title')->vectorFields()->first()->name;

        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document([
                'title' => 'guide', 'group' => 'a', 'order' => 1, '_embeddings' => ['title' => [$field => $vector]],
            ], _id: 'first'),
            new Document([
                'title' => 'guide', 'group' => 'a', 'order' => 2, '_embeddings' => ['title' => [$field => $opposite]],
            ], _id: 'below'),
            new Document([
                'title' => 'guide', 'group' => 'a', 'order' => 3, '_embeddings' => ['title' => [$field => $vector]],
            ], _id: 'last'),
        ]);

        $result = $this->sigmie->newSearch($index)->properties($props)->semantic()
            ->queryString('guide', vector: $vector)->minScore(0.8)
            ->sort('order:asc')->uniqueBy('group', top: 2)->get();

        $this->assertSame('first', $result->json('hits.0._id'));
        $this->assertSame(['last'], array_column($result->json('hits.0.inner_hits.top.hits.hits'), '_id'));
    }

    public function test_facet_selection_filters_live_hits_and_preserves_its_own_counts(): void
    {
        $index = uniqid('facet_selection_');
        $props = new NewProperties;
        $props->keyword('brand');
        $props->keyword('color');

        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->merge([
            new Document(['brand' => 'dell', 'color' => 'red'], _id: 'dell'),
            new Document(['brand' => 'hp', 'color' => 'blue'], _id: 'hp'),
        ]);

        $result = $this->sigmie->newSearch($index)->facets('brand color', "brand:'dell'")
            ->properties($props)->get();

        $this->assertSame(['dell'], array_column($result->hits(), '_id'));
        $this->assertSame(['dell' => 1, 'hp' => 1], $result->facet('brand'));
        $this->assertSame(['red' => 1], $result->facet('color'));
    }

    public function test_collapsed_inner_hits_omit_excluded_source_fields(): void
    {
        $index = uniqid('collapse_excludes_');
        $props = new NewProperties;
        $props->keyword('group');
        $props->keyword('internal_note');
        $props->number('rank');

        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->merge([
            new Document(['group' => 'one', 'internal_note' => 'keep private', 'rank' => 1], _id: 'one'),
            new Document(['group' => 'one', 'internal_note' => 'keep private', 'rank' => 2], _id: 'two'),
        ]);

        $result = $this->sigmie->newSearch($index)->properties($props)
            ->sort('rank:asc')->uniqueBy('group', top: 1)->except(['internal_note'])->get();

        $top = $result->json('hits.0.inner_hits.top.hits.hits');
        $this->assertSame('two', $top[0]['_id']);
        $this->assertArrayNotHasKey('internal_note', $top[0]['_source']);
    }

    public function test_vector_only_search_can_request_no_hits(): void
    {
        $this->forOpensearch(fn () => $this->markTestSkipped('Elasticsearch top-level kNN request'));

        $index = uniqid('no_vector_hits_');
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 128);

        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document(['title' => 'first'], _id: 'first'),
            new Document(['title' => 'second'], _id: 'second'),
        ]);

        $vector = $this->embeddingApi->embed('first', 128);
        $result = $this->sigmie->newSearch($index)->properties($props)
            ->semantic()->disableKeywordSearch()
            ->queryString('first', vector: $vector)->size(0)->get();

        $this->assertSame([], $result->hits());
        // Both documents are vector neighbours; the total does not depend on the page size.
        $this->assertSame(2, $result->total());
    }

    public function test_field_type_audit_matches_search_as_you_type_prefixes(): void
    {
        $props = new NewProperties;
        $props->searchAsYouType('headline');

        $this->withFieldTypeDocuments($props, ['fox' => ['headline' => 'Quick brown fox']], function (NewSearch $search): void {
            $this->assertSame(['fox'], array_column($search->queryString('fo')->get()->hits(), '_id'));
        });
    }

    public function test_field_type_audit_matches_uppercase_email_prefixes(): void
    {
        $props = new NewProperties;
        $props->email('email');

        $this->withFieldTypeDocuments($props, ['jane' => ['email' => 'Jane.Doe@Example.com']], function (NewSearch $search): void {
            $this->assertSame(['jane'], array_column($search->queryString('JAN')->get()->hits(), '_id'));
        });
    }

    public function test_field_type_audit_searches_the_whole_path_prefix(): void
    {
        $props = new NewProperties;
        $props->path('location');

        $this->withFieldTypeDocuments($props, [
            'tax' => ['location' => '/Assets/Reports/ΟΣΧ'],
            'readme' => ['location' => '/Assets/Readme'],
        ], function (NewSearch $search): void {
            $this->assertSame(['tax'], array_column($search->queryString('/ASSETS/REPORTS/ΟΣ')->get()->hits(), '_id'));
        });
    }

    public function test_field_type_audit_keeps_all_letters_in_name_prefixes(): void
    {
        $props = new NewProperties;
        $props->name('name');

        $this->withFieldTypeDocuments($props, [
            'mina' => ['name' => 'Mina'],
            'minerva' => ['name' => 'Minerva'],
        ], function (NewSearch $search): void {
            $this->assertSame(['mina'], array_column($search->queryString('Mina')->get()->hits(), '_id'));
        });
    }

    public function test_field_type_audit_highlights_indexed_address_prefixes(): void
    {
        $props = new NewProperties;
        $props->address('address');

        $this->withFieldTypeDocuments($props, ['lyon' => ['address' => '42 Rue de Lyon']], function (NewSearch $search): void {
            $hits = $search->queryString('LY')->highlighting(['address'], '<mark>', '</mark>')->get()->json('hits');
            $this->assertSame(['42 Rue de <mark>Lyon</mark>'], $hits[0]['highlight']['address']);
        });
    }

    public function test_field_type_audit_handles_whitespace_like_an_empty_search(): void
    {
        $props = new NewProperties;
        $props->text('title');

        $this->withFieldTypeDocuments($props, ['fox' => ['title' => 'Quick brown fox']], function (NewSearch $search): void {
            $this->assertSame(['fox'], array_column($search->queryString(" \t ")->get()->hits(), '_id'));
            $this->assertSame([], $search->noResultsOnEmptySearch()->get()->hits());
        });
    }

    public function test_field_type_audit_runs_custom_match_queries_on_keywords(): void
    {
        $props = new NewProperties;
        $props->keyword('code')->withQueries(fn (string $query): array => [
            new Match_('code', $query),
        ]);

        $this->withFieldTypeDocuments($props, ['sku' => ['code' => 'FR42']], function (NewSearch $search): void {
            $this->assertSame(['sku'], array_column($search->queryString('fr42')->get()->hits(), '_id'));
        });
    }

    private function withFieldTypeDocuments(NewProperties $props, array $documents, callable $assert): void
    {
        $index = uniqid('.type-search-');
        $this->sigmie->newIndex($index)->properties($props)->create();

        try {
            $collection = $this->sigmie->collect($index, refresh: true)->properties($props);
            $collection->merge(array_map(
                fn (array $source, string $id): Document => new Document($source, _id: $id),
                array_values($documents),
                array_keys($documents),
            ));

            $assert($this->sigmie->newSearch($index)->properties($props));
        } finally {
            $this->sigmie->deleteIfExists($index);
        }
    }

    /**
     * @test
     */
    public function weighted_query_string(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Goofy']),
            new Document(['name' => 'Donald']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->textScoreMultiplier(2)
            ->semanticScoreMultiplier(0.5)
            ->queryString('Mickey', weight: 2)
            ->queryString('Goofy', weight: 1);

        $this->assertSame(['Mickey', 'Goofy'], array_map(
            fn (object $queryString): string => (string) $queryString,
            $search->queryStrings()
        ));
        $this->assertSame(['name'], $search->getProperties()?->fieldNames());

        $res = $search->get();

        $hits = $res->json('hits');

        $this->assertEquals('Mickey', $hits[0]['_source']['name']);
        $this->assertEquals(2, $res->total());

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Mickey', weight: 2)
            ->queryString('Goofy', weight: 3)
            ->get();

        $hits = $res->json('hits');

        $this->assertEquals('Goofy', $hits[0]['_source']['name']);
        $this->assertEquals(2, $res->total());

        $raw = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->formatter(new RawElasticsearchFormat)
            ->queryString('Donald')
            ->get();

        $this->assertSame('Donald', $raw->format()['hits']['hits'][0]['_source']['name']);
    }

    /**
     * @test
     */
    public function from_sets_the_elasticsearch_offset(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'alpha'], _id: '1'),
            new Document(['name' => 'bravo'], _id: '2'),
            new Document(['name' => 'charlie'], _id: '3'),
        ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->sort('name:asc')
            ->from(1)
            ->size(1)
            ->get()
            ->hits();

        $this->assertCount(1, $hits);
        $this->assertSame('bravo', $hits[0]->_source['name']);
    }

    /**
     * @test
     */
    public function search_after_returns_the_next_hit(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'alpha'], _id: '1'),
            new Document(['name' => 'bravo'], _id: '2'),
            new Document(['name' => 'charlie'], _id: '3'),
        ]);

        $first = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->sort('name:asc')
            ->size(1)
            ->get()
            ->hits();

        $this->assertSame('alpha', $first[0]->_source['name']);
        $this->assertNotNull($first[0]->sort);

        $next = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->sort('name:asc')
            ->size(1)
            ->searchAfter($first[0]->sort)
            ->get()
            ->hits();

        $this->assertCount(1, $next);
        $this->assertSame('bravo', $next[0]->_source['name']);
    }

    /**
     * @test
     */
    public function autocomplete_prefix_returns_completion_suggestions_from_elasticsearch(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->completion('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Star Trek']),
                new Document(['title' => 'Star Wars']),
                new Document(['title' => 'Stargate']),
                new Document(['title' => 'Moonrise']),
            ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->autocompletePrefix('star')
            ->autocompleteSize(3)
            ->queryString('')
            ->get();

        $suggestions = array_map(
            fn (array $option): string => $option['text'],
            $res->autocompletion()[0]['options']
        );

        sort($suggestions);

        $this->assertSame(['Star Trek', 'Star Wars', 'Stargate'], $suggestions);
    }

    /**
     * @test
     */
    public function search_response_helpers_reflect_elasticsearch_results(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->keyword('category');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Alpha Search Manual', 'category' => 'docs'], _id: 'alpha'),
                new Document(['title' => 'Beta Archive', 'category' => 'archive'], _id: 'beta'),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Alpha')
            ->fields(['title'])
            ->facets('category')
            ->size(1)
            ->get();

        $format = $response->format();
        $hits = $response->hits();
        $reranked = $response->rerank('test-rerank', ['title']);
        $rerankedWithApi = $response->rerank($this->rerankApi, ['title'], query: 'Alpha', topK: 1);
        $scores = $this->rerankApi->rerank(['Alpha Search Manual', 'Beta Archive'], 'Alpha');
        $this->rerankApi->reset();
        $matchAllResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->size(1)
            ->get();
        $matchAllReranked = $matchAllResponse->rerank($this->rerankApi, ['title'], topK: 1);

        $this->assertSame('alpha', $response->json('hits')[0]['_id']);
        $this->assertSame('alpha', $hits[0]->_id);
        $this->assertInstanceOf(Hit::class, $hits[0]);
        $this->assertEquals(1, $response->total());
        $this->assertSame(['Alpha'], $format['query_strings']);
        $this->assertSame('category', $format['facets_string']);
        $this->assertSame(1, $format['page']);
        $this->assertSame(1, $format['per_page']);
        $this->assertNotNull($response->getContext());
        $this->assertInstanceOf(RerankedHit::class, $reranked[0]);
        $this->assertSame('alpha', $reranked[0]->_id);
        $this->assertSame([], $response->autocompletion());
        $this->assertInstanceOf(RerankedHit::class, $rerankedWithApi[0]);
        $this->assertSame([0, 1], array_column($scores, 'index'));
        $this->assertInstanceOf(Hit::class, $matchAllReranked[0]);
        $this->assertContains($matchAllReranked[0]->_id, ['alpha', 'beta']);
        $this->rerankApi->assertRerankWasCalled(0);
    }

    /**
     * @test
     */
    public function search_response_rerank_validation_is_backed_by_elasticsearch_results(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Validation Manual'], _id: 'matching'),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('Validation')
            ->get();

        $hits = $response->hits();

        $this->assertSame(['matching'], array_map(fn (Hit $hit): string => $hit->_id, $hits));
        $this->assertSame(0, $this->rerankApi->rerank(['Validation Manual'], 'Validation')[0]['index']);
        $this->assertSame('matching', $response->rerank($this->rerankApi, ['title'], topK: 1)[0]->_id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Registered API "invalid-rerank" is not a RerankApi.');

        $response->apis(['invalid-rerank' => (object) []])
            ->rerank('invalid-rerank', ['title']);
    }

    /** @test */
    public function standalone_facet_search_uses_the_configured_query(): void
    {
        $name = uniqid('facet_search_');
        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->keyword('category');

        $this->sigmie->newIndex($name)->properties($blueprint)->create();
        $this->sigmie->collect($name, refresh: true)->merge([
            new Document(['title' => 'Orchid', 'category' => 'flower']),
            new Document(['title' => 'Cedar', 'category' => 'tree']),
        ]);

        $search = $this->sigmie->newSearch($name)
            ->properties($blueprint)
            ->queryString('Orchid')
            ->facets('category');

        $aggregations = $search->makeFacetSearch()->get()->get()['aggregations'];
        $this->assertSame(['flower' => 1], $blueprint->get()->get('category')->facets($aggregations['category']));
    }

    /**
     * @test
     */
    public function make_facet_search_returns_filtered_elasticsearch_aggregations(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');
        $blueprint->bool('active');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['category' => 'docs', 'active' => true]),
                new Document(['category' => 'docs', 'active' => false]),
                new Document(['category' => 'blog', 'active' => true]),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->filters('active:true')
            ->facets('category')
            ->makeFacetSearch()
            ->get();

        $facets = $blueprint->get()['category']->facets($response->get()['aggregations']['category']);

        $this->assertSame(['blog' => 1, 'docs' => 1], $facets);
    }

    /**
     * @test
     */
    public function semantic_search_uses_seeded_vector_pool_and_returns_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 128, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Alpha search manual'], _id: 'alpha'),
                new Document(['title' => 'Beta archive notes'], _id: 'beta'),
            ]);

        $vectorPool = new VectorPool($this->embeddingApi);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->queryString('Alpha search manual')
            ->fields(['title'])
            ->setVectorPool($vectorPool)
            ->setVectorPool([
                'Gamma cache seed' => [
                    128 => $this->embeddingApi->embed('Gamma cache seed', 128),
                ],
            ]);

        $this->assertSame($vectorPool, $search->getVectorPool('test-embeddings'));
        $this->assertSame($vectorPool, $search->getVectorPool());
        $this->assertArrayHasKey('test-embeddings', $search->getVectorPools());

        $hits = $search->size(1)->hits();

        $this->assertSame('alpha', $hits[0]->_id);
        $this->embeddingApi->assertEmbedWasCalled(1);
        $this->embeddingApi->assertEmbedWasCalledWith('Gamma cache seed');
        $this->embeddingApi->assertEmbedWasCalledWith('Gamma cache seed', 128);

        $response = $this->embeddingApi->promiseEmbed('Alpha search manual', 128)->wait();
        $this->assertCount(128, json_decode((string) $response->getBody(), true)['data'][0]['embedding']);
        $this->embeddingApi->assertEmbedWasCalled(2);
        $this->embeddingApi->assertEmbedWasCalled();
        $this->assertNotSame('', $this->embeddingApi->model());
        $this->assertGreaterThan(0, $this->embeddingApi->maxBatchSize());
        $this->assertSame(2, $this->embeddingApi->overrideMaxBatchSize(2)->maxBatchSize());
    }

    /**
     * @test
     */
    public function quantized_semantic_fields_retrieve_the_nearest_document(): void
    {
        $this->forOpenSearch(fn () => $this->markTestSkipped('OpenSearch supports only the hnsw vector index type'));

        foreach (['bbq_hnsw', 'int8_hnsw'] as $indexType) {
            $indexName = uniqid();

            $blueprint = new NewProperties;
            $blueprint->text('title')->newSemantic(function (NewSemanticField $semantic) use ($indexType): void {
                $semantic->api('test-embeddings')->dimensions(128)->indexType($indexType);
            });

            $this->sigmie->newIndex($indexName)
                ->properties($blueprint)
                ->create();

            $this->assertIndex($indexName, function (Assert $assert) use ($indexType): void {
                $field = $assert->data()['mappings']['properties']['_embeddings']['properties']['title']['properties'][$indexType.'_m64_efc300_dims128_cosine_concat'];

                $this->assertSame($indexType, $field['index_options']['type']);
            });

            $this->sigmie->collect($indexName, refresh: true)
                ->properties($blueprint)
                ->merge([
                    new Document(['title' => 'Alpha search manual'], _id: 'alpha'),
                    new Document(['title' => 'Beta archive notes'], _id: 'beta'),
                    new Document(['title' => 'Gamma cooking recipes'], _id: 'gamma'),
                ]);

            $hits = $this->sigmie->newSearch($indexName)
                ->properties($blueprint)
                ->semantic()
                ->disableKeywordSearch()
                ->queryString('Alpha search manual')
                ->fields(['title'])
                ->hits();

            $ids = array_map(fn (Hit $hit): string => $hit->_id, $hits);

            $this->assertSame('alpha', $ids[0], $indexType);
            $this->assertGreaterThan(0, array_search('beta', $ids, true), $indexType);
        }
    }

    /**
     * @test
     */
    public function semantic_search_without_embedding_api_falls_back_to_elasticsearch_keyword_hits(): void
    {
        $indexName = uniqid();

        $indexBlueprint = new NewProperties;
        $indexBlueprint->text('title');

        $searchBlueprint = new NewProperties;
        $searchBlueprint->text('title')->newSemantic(fn ($semantic) => $semantic->accuracy(1, 128));

        $this->sigmie->newIndex($indexName)
            ->properties($indexBlueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($indexBlueprint)
            ->merge([
                new Document(['title' => 'API-less semantic fallback'], _id: 'matching'),
                new Document(['title' => 'Other document'], _id: 'missing'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($searchBlueprint)
            ->semantic()
            ->queryString('fallback')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $search = new class($this->elasticsearchConnection) extends NewSearch
        {
            public function noResultsEmptyQuery(): array
            {
                $this->noResultsOnEmptySearch();

                return $this->onEmptyQueryString()->toRaw();
            }

            public function vectorDimensions(): array
            {
                return $this->getVectorDimensions(new Collection([
                    new BaseVector('first', dims: 3),
                    new BaseVector('second', dims: 3),
                    new BaseVector('third', dims: 5),
                ]));
            }
        };

        $emptyQuery = $search->noResultsEmptyQuery();

        $this->assertArrayHasKey('match_none', $emptyQuery);
        $this->assertSame(1.0, $emptyQuery['match_none']->boost);
        $this->assertSame([3, 5], array_values($search->vectorDimensions()));
    }

    /**
     * @test
     */
    public function field_scoped_query_string(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->text('category');
        $blueprint->text('description');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['name' => 'Laptop', 'category' => 'Electronics', 'description' => 'High-end device']),
                new Document(['name' => 'Phone', 'category' => 'Electronics', 'description' => 'Mobile phone']),
                new Document(['name' => 'Electronics', 'category' => 'Furniture', 'description' => 'Office desk']),
            ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['name', 'category'])
            ->queryString('Electronics', 1.0, ['category'])
            ->makeSearch();

        $hits = $search->get()->hits();

        // Only two have category Electronics the third one
        // has category Furniture, and Elactronies in name
        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function find_without_dash(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->searchableNumber('number');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'number' => '08000234379',
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('0800-0234379')
            ->get();

        $this->assertNotEmpty($res->hits());
    }

    /**
     * @test
     */
    public function handle_boost_missing_gracefully(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->date('date');

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'date' => '2024-01-01',
            ]),
        ]);

        $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->sort('_score')
            ->queryString('')
            ->get();

        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function empty_on_non_queriable_field(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->date('date');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'date' => '2024-01-01',
            ]),
        ]);

        $saved = $this->sigmie->newSearch($indexName)
            ->fields(['date'])
            ->properties($blueprint)
            ->queryString('123');

        $hits = $saved->get()->json('hits');

        $this->assertEmpty($hits);
    }

    /**
     * @test
     */
    public function id_search(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->id('id');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'id' => '123',
            ]),
        ]);

        $saved = $this->sigmie->newSearch($indexName)
            ->fields(['id'])
            ->properties($blueprint)
            ->queryString('123');

        $hits = $saved->get()->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function nested_retrieve(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->name('name');
        $blueprint->nested('contact', function (NewProperties $blueprint): void {
            $blueprint->name('name');
            $blueprint->nested('dog', function (NewProperties $blueprint): void {
                $blueprint->name('name');
            });
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey',
                'contact' => [
                    'name' => 'Mickey',
                    'dog' => [
                        'name' => 'Pluto',
                    ],
                ],
            ]),
        ]);

        $saved = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Pluto')
            ->fields(['contact.dog.name'])
            ->retrieve(['contact.dog.name']);

        $response = $saved->get();

        $hits = $response->json('hits');

        $this->assertNull($hits[0]['_source']['contact']['name'] ?? null);
        $this->assertNotNull($hits[0]['_source']['contact']['dog']['name'] ?? null);
    }

    /**
     * @test
     */
    public function query_string_matches_text_inside_nested_fields(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('slug');
        $blueprint->nested('sections', function (NewProperties $blueprint): void {
            $blueprint->text('text');
        });

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['slug' => 'energy-at-home', 'sections' => [['text' => 'The solar panels were installed.'], ['text' => 'The heat pump was kept.']]]),
        ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('solar')
            ->get()
            ->json('hits');

        $this->assertCount(1, $hits);
    }

    /**
     * A phone with 12 reviews: 0-9 mention "battery", 10-11 do not. Even reviews have 5 stars,
     * odd ones 2. Eight questions, 0-6 about the battery. Six answers, all about the battery.
     * A laptop without battery reviews.
     *
     * @return array{0: string, 1: NewProperties}
     */
    private function createProductsWithReviews(): array
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->title('title');
        $blueprint->nested('reviews', function (NewProperties $blueprint): void {
            $blueprint->text('comment');
            $blueprint->number('stars');
            $blueprint->keyword('author');
        });
        $blueprint->nested('questions', function (NewProperties $blueprint): void {
            $blueprint->text('text');
        });
        $blueprint->nested('answers', function (NewProperties $blueprint): void {
            $blueprint->text('text');
        });

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $reviews = array_map(fn (int $i): array => [
            'comment' => $i < 10 ? sprintf('Review %d: the battery lasts', $i) : sprintf('Review %d: sharp screen', $i),
            'stars' => $i % 2 === 0 ? 5 : 2,
            'author' => 'author-'.$i,
        ], range(0, 11));

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document([
                'title' => 'Phone X',
                'reviews' => $reviews,
                'questions' => [
                    ...array_map(fn (int $i): array => ['text' => sprintf('Question %d about the battery?', $i)], range(0, 6)),
                    ['text' => 'Which colors exist?'],
                ],
                'answers' => array_map(fn (int $i): array => ['text' => sprintf('Answer %d: the battery is sealed.', $i)], range(0, 5)),
            ], 'phone'),
            new Document([
                'title' => 'Laptop Y',
                'reviews' => [['comment' => 'Great keyboard', 'stars' => 5, 'author' => 'author-x']],
                'questions' => [],
                'answers' => [],
            ], 'laptop'),
        ]);

        return [$indexName, $blueprint];
    }

    /**
     * @test
     */
    public function inner_hits_return_all_matching_nested_items_only_when_requested(): void
    {
        [$indexName, $blueprint] = $this->createProductsWithReviews();

        $search = fn (): NewSearch => $this->sigmie->newSearch($indexName)->properties($blueprint)->queryString('battery');

        $plain = $search()->get()->json('hits');

        $this->assertArrayNotHasKey('_matches', $plain[0]);
        $this->assertArrayNotHasKey('inner_hits', $plain[0]);

        $hit = $search()->innerHits(['reviews'])->get()->json('hits')[0];

        $this->assertSame('phone', $hit['_id']);
        $this->assertCount(12, $hit['_source']['reviews']);
        $this->assertSame(['reviews'], array_keys($hit['_matches']));
        $this->assertSame(10, $hit['_matches']['reviews']['total']);
        $this->assertEqualsCanonicalizing(range(0, 9), array_column($hit['_matches']['reviews']['items'], '_offset'));
        $this->assertEqualsCanonicalizing(['_offset', 'comment', 'stars', 'author'], array_keys($hit['_matches']['reviews']['items'][0]));
        $this->assertArrayNotHasKey('inner_hits', $hit);
    }

    /**
     * @test
     */
    public function inner_hits_select_fields_of_the_matching_items_with_dot_syntax(): void
    {
        [$indexName, $blueprint] = $this->createProductsWithReviews();

        $hit = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('battery')
            ->retrieve(['title'])
            ->innerHits(['reviews.comment', 'reviews.stars'])
            ->get()
            ->json('hits')[0];

        $this->assertSame(['title' => 'Phone X'], $hit['_source']);

        foreach ($hit['_matches']['reviews']['items'] as $item) {
            $this->assertEqualsCanonicalizing(['_offset', 'comment', 'stars'], array_keys($item));
        }
    }

    /**
     * @test
     */
    public function inner_hits_size_caps_the_items_while_total_counts_every_match(): void
    {
        [$indexName, $blueprint] = $this->createProductsWithReviews();

        $matches = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('battery')
            ->innerHits(['reviews'], size: 4)
            ->get()
            ->json('hits.0._matches.reviews');

        $this->assertSame(10, $matches['total']);
        $this->assertCount(4, $matches['items']);
    }

    /**
     * @test
     */
    public function inner_hits_array_form_returns_several_paths_sharing_one_size(): void
    {
        [$indexName, $blueprint] = $this->createProductsWithReviews();

        $matches = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('battery')
            ->innerHits(['reviews.comment', 'questions'], size: 3)
            ->get()
            ->json('hits.0._matches');

        $this->assertSame(['reviews', 'questions'], array_keys($matches));
        $this->assertSame(10, $matches['reviews']['total']);
        $this->assertCount(3, $matches['reviews']['items']);
        $this->assertSame(7, $matches['questions']['total']);
        $this->assertCount(3, $matches['questions']['items']);
        $this->assertEqualsCanonicalizing(['_offset', 'text'], array_keys($matches['questions']['items'][0]));
    }

    /**
     * @test
     */
    public function inner_hits_chained_calls_return_each_path_with_its_own_size(): void
    {
        [$indexName, $blueprint] = $this->createProductsWithReviews();

        $matches = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('battery')
            ->innerHits('reviews', size: 10)
            ->innerHits('questions.text', size: 5)
            ->innerHits('answers')
            ->get()
            ->json('hits.0._matches');

        $this->assertSame(['reviews', 'questions', 'answers'], array_keys($matches));
        $this->assertSame([10, 10], [$matches['reviews']['total'], count($matches['reviews']['items'])]);
        $this->assertSame([7, 5], [$matches['questions']['total'], count($matches['questions']['items'])]);
        $this->assertSame([6, 6], [$matches['answers']['total'], count($matches['answers']['items'])]);
        $this->assertEqualsCanonicalizing(['_offset', 'comment', 'stars', 'author'], array_keys($matches['reviews']['items'][0]));
    }

    /**
     * @test
     */
    public function hits_expose_matches_per_nested_path(): void
    {
        [$indexName, $blueprint] = $this->createProductsWithReviews();

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('battery')
            ->innerHits('reviews', size: 10)
            ->innerHits('questions.text', size: 5)
            ->innerHits('answers')
            ->get()
            ->hits();

        $this->assertCount(1, $hits);

        foreach ($hits as $hit) {
            $this->assertCount(10, $hit->matches('reviews'));
            $this->assertSame(10, $hit->matchesTotal('reviews'));
            $this->assertCount(5, $hit->matches('questions'));
            $this->assertSame(7, $hit->matchesTotal('questions'));
            $this->assertSame($hit->matches('questions'), $hit->matches('questions.text'));
            $this->assertSame(6, $hit->matchesTotal('answers'));
            $this->assertSame(['reviews', 'questions', 'answers'], array_keys($hit->matches()));
            $this->assertSame($hit->matches('reviews'), $hit->matches()['reviews']);
            $this->assertStringContainsString('battery', $hit->matches('reviews')[0]['comment']);

            $this->assertSame([], $hit->matches('missing'));
            $this->assertSame(0, $hit->matchesTotal('missing'));
            $this->assertSame([], $hit->matches('title'));

            $this->assertSame(10, $hit->toArray()['_matches']['reviews']['total']);
            $this->assertSame(7, (new RerankedHit($hit, 0.5))->matchesTotal('questions'));
        }

        $plain = $this->sigmie->newSearch($indexName)->properties($blueprint)->queryString('battery')->get()->hits()[0];

        $this->assertSame([], $plain->matches());
        $this->assertSame([], $plain->matches('reviews'));
        $this->assertSame(0, $plain->matchesTotal('reviews'));
        $this->assertArrayNotHasKey('_matches', $plain->toArray());
    }

    /**
     * @test
     */
    public function inner_hits_for_the_same_path_merge_fields_and_the_later_size_wins(): void
    {
        [$indexName, $blueprint] = $this->createProductsWithReviews();

        $search = fn (): NewSearch => $this->sigmie->newSearch($indexName)->properties($blueprint)->queryString('battery');

        $fields = $search()
            ->innerHits('reviews.comment', size: 2)
            ->innerHits('reviews.stars', size: 4)
            ->get()
            ->json('hits.0._matches.reviews');

        $this->assertSame(10, $fields['total']);
        $this->assertCount(4, $fields['items']);
        $this->assertEqualsCanonicalizing(['_offset', 'comment', 'stars'], array_keys($fields['items'][0]));

        $whole = $search()
            ->innerHits('reviews.comment', size: 8)
            ->innerHits('reviews', size: 1)
            ->get()
            ->json('hits.0._matches.reviews');

        $this->assertCount(1, $whole['items']);
        $this->assertEqualsCanonicalizing(['_offset', 'comment', 'stars', 'author'], array_keys($whole['items'][0]));
    }

    /**
     * @test
     */
    public function inner_hits_merge_query_and_nested_filter_matches_without_duplicates(): void
    {
        [$indexName, $blueprint] = $this->createProductsWithReviews();

        // Query: reviews 0-9. Filter: even reviews 0-10. Union: 0-10, the even ones match both.
        $matches = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('battery')
            ->filters('reviews:{stars>=4}')
            ->innerHits(['reviews.stars'])
            ->get()
            ->json('hits.0._matches.reviews');

        $this->assertSame(11, $matches['total']);
        $this->assertEqualsCanonicalizing(range(0, 10), array_column($matches['items'], '_offset'));
    }

    /**
     * @test
     */
    public function inner_hits_leave_out_fields_of_except(): void
    {
        [$indexName, $blueprint] = $this->createProductsWithReviews();

        $items = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('battery')
            ->except(['reviews.author'])
            ->innerHits(['reviews'])
            ->get()
            ->json('hits.0._matches.reviews.items');

        $this->assertCount(10, $items);

        foreach ($items as $item) {
            $this->assertArrayNotHasKey('author', $item);
        }
    }

    /**
     * @test
     */
    public function inner_hits_return_deeper_nested_paths_with_their_parent_offsets(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('orders', function (NewProperties $blueprint): void {
            $blueprint->keyword('number');
            $blueprint->nested('items', function (NewProperties $blueprint): void {
                $blueprint->keyword('sku');
                $blueprint->keyword('note');
            });
        });

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['orders' => [
                ['number' => 'A', 'items' => [['sku' => 'x', 'note' => 'n1'], ['sku' => 'y', 'note' => 'n2']]],
                ['number' => 'B', 'items' => [['sku' => 'y', 'note' => 'n3']]],
            ]]),
        ]);

        $matches = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->filters("orders:{items:{sku:'y'}}")
            ->innerHits(['orders.items.sku'])
            ->get()
            ->json('hits.0._matches');

        $this->assertSame(['orders.items'], array_keys($matches));
        $this->assertSame(2, $matches['orders.items']['total']);
        $this->assertEqualsCanonicalizing([
            ['_offset' => 1, '_parents' => ['orders' => 0], 'sku' => 'y'],
            ['_offset' => 0, '_parents' => ['orders' => 1], 'sku' => 'y'],
        ], $matches['orders.items']['items']);
    }

    /**
     * @test
     *
     * @dataProvider invalidInnerHits
     */
    public function inner_hits_reject_invalid_arguments(array $fields, int $size, string $message): void
    {
        [$indexName, $blueprint] = $this->createProductsWithReviews();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->sigmie->newSearch($indexName)->properties($blueprint)->queryString('battery')->innerHits($fields, $size)->get();
    }

    /**
     * @return array<string, array{0: list<string>, 1: int, 2: string}>
     */
    public static function invalidInnerHits(): array
    {
        return [
            'field outside nested fields' => [['title'], 100, "Field 'title' is not a nested field or inside one."],
            'unknown field' => [['reviews.missing'], 100, "Field 'reviews.missing' does not exist."],
            'size below one' => [['reviews'], 0, 'Inner hits size must be at least 1.'],
        ];
    }

    /**
     * @test
     */
    public function nested_name_property(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->name('name');
        $blueprint->nested('contact', function (NewProperties $blueprint): void {
            $blueprint->name('name');
            $blueprint->nested('dog', function (NewProperties $blueprint): void {
                $blueprint->name('name');
            });
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey',
                'contact' => [
                    'name' => 'Mickey',
                    'dog' => [
                        'name' => 'Pluto',
                    ],
                ],
            ]),
        ]);

        $saved = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Pluto')
            ->fields(['contact.dog.name']);

        $hits = $saved->get()->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function min_score(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->title();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'title' => 'Mickey',
            ]),
        ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->weight([
                'title' => 10,
            ])
            ->minScore(2)
            ->queryString('Mickey')
            ->get();

        $hits = $response->hits();

        $this->assertGreaterThan(2, $hits[0]->_score ?? 0);
    }

    /**
     * @test
     */
    public function price_facet(): void
    {

        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->price();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $res = $this->indexAPICall($indexName, 'GET');

        $this->assertEquals('price', $res->json($index->name.'.mappings.properties.price.meta.type'));

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['price' => 200]),
            new Document(['price' => 100]),
            new Document(['price' => 50]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('price')
            ->get();

        $price = (array) $search->json('facets')['price'];

        $buckets = $price['histogram'] ?? [];

        $this->assertCount(16, $buckets);

        $this->assertEquals(50, $price['min']);
        $this->assertEquals(200, $price['max']);
    }

    /**
     * @test
     */
    public function empty_results_on_empty_string(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->name('name');

        $index = $this->sigmie->newIndex($indexName)
            ->trim()
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Jason Preston',
                'autocomplete' => [''],
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->get();

        $hits = $res->json('hits');

        $this->assertNotEmpty($hits);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->noResultsOnEmptySearch()
            ->queryString('')
            ->get();

        $hits = $res->json('hits');

        $this->assertEmpty($hits);
    }

    /**
     * @test
     */
    public function name_prefix_search_test(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->name('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Jason Preston',
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('jas')
            ->fields(['name'])
            ->retrieve(['name'])
            ->get();

        $this->sigmie->index($indexName)->raw;

        $hits = $res->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function name_two_words_search_test(): void
    {
        $documentId = uniqid();
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->name('first_name');
        $blueprint->name('last_name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'first_name' => 'Bam',
                'last_name' => 'Adebayo',
            ], $documentId),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('bam ade')
            ->fields(['first_name', 'last_name'])
            ->retrieve(['first_name', 'last_name'])
            ->makeSearch();

        $res = $search->get();

        $hits = $res->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function name_search_test(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->name('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Jason Preston',
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('jas')
            ->fields(['name'])
            ->retrieve(['name'])
            ->get();

        $hits = $res->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function search_promises_test(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->text('description');

        $props = ($blueprint)();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->lowercase()
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey',
                'description' => 'Adventure in the woods',
            ]),
            new Document([
                'name' => 'Goofy',
                'description' => 'Mickey and his friends',
            ]),
            new Document([
                'name' => 'Donald',
                'description' => 'Chasing Goofy',
            ]),
        ]);

        $promise = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->queryString('mickey')
            ->fields(['name'])
            ->retrieve(['name', 'description'])
            ->promise();

        $this->assertInstanceOf(Promise::class, $promise);
    }

    /**
     * @test
     */
    public function with_weight(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->text('description')->searchAsYouType();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey',
                'description' => 'Adventure in the woods',
            ]),
            new Document([
                'name' => 'Goofy',
                'description' => 'Mickey and his friends',
            ]),
            new Document([
                'name' => 'Donald',
                'description' => 'Chasing Goofy',
            ]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('Mickey')
            ->weight([
                'name' => 5,
                'description' => 1,
            ])
            ->fields(['name', 'description'])
            ->sort('_score')
            ->get();

        $hits = $search->json('hits');

        $this->assertEquals('Mickey', $hits[0]['_source']['name']);
        $this->assertCount(2, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('Mickey')
            ->fields(['name', 'description'])
            ->sort('_score')
            ->weight([
                'name' => 1,
                'description' => 5,
            ])
            ->get();

        $hits = $search->json('hits');

        $this->assertEquals('Goofy', $hits[0]['_source']['name']);
        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function typo_tolerance_with_one_typo(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->category('category');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Mickey', 'category' => 'cartoon']),
            new Document(['name' => 'Goofy', 'category' => 'cartoon']),
            new Document(['name' => 'Donald', 'category' => 'cartoon']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('Mockey')
            ->facets('category')
            ->fields(['name'])
            ->typoTolerance()
            ->typoTolerantAttributes([
                'name',
            ])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(1, $hits);
        $this->assertSame('Mickey', $hits[0]['_source']['name']);
    }

    /**
     * @test
     */
    public function custom_typo_tolerance_thresholds_return_expected_elasticsearch_hit(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'Mickey'], _id: 'mickey'),
            new Document(['name' => 'Donald'], _id: 'donald'),
        ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['name'])
            ->typoTolerantAttributes(['name'])
            ->minCharsForOneTypo(1)
            ->minCharsForTwoTypo(4)
            ->queryString('Mickeu')
            ->get();

        $this->assertSame(['mickey'], array_map(
            fn (array $hit): string => $hit['_id'],
            $response->json('hits')
        ));
    }

    /**
     * @test
     */
    public function typo_tolerance_without_typo(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Goofy']),
            new Document(['name' => 'Donald']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('Mockey')
            ->fields(['name'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(0, $hits);
    }

    /**
     * @test
     */
    public function source(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->text('category')->keyword();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => 'a', 'name' => 'Mickey']),
            new Document(['category' => 'b', 'name' => 'Goofy']),
            new Document(['category' => 'c', 'name' => 'Donald']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('a')
            ->retrieve(['name'])
            ->fields(['category'])
            ->get();

        $hits = $search->json('hits');

        $this->assertArrayHasKey('name', $hits[0]['_source']);
        $this->assertArrayNotHasKey('category', $hits[0]['_source']);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('a')
            ->fields(['category'])
            ->get();

        $hits = $search->json('hits');

        $this->assertArrayHasKey('name', $hits[0]['_source']);
        $this->assertArrayHasKey('category', $hits[0]['_source']);
    }

    /**
     * @test
     */
    public function highlight(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('category')->keyword();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => 'a']),
            new Document(['category' => 'b']),
            new Document(['category' => 'c']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('a')
            ->highlighting(['category'], '<span class="font-bold">', '</span>')
            ->fields(['category'])
            ->get();

        $hits = $search->json('hits');

        $this->assertEquals('<span class="font-bold">a</span>', $hits[0]['highlight']['category'][0]);
    }

    /**
     * @test
     */
    public function sort(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('category')->keyword()->makeSortable();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => 'a']),
            new Document(['category' => 'b']),
            new Document(['category' => 'c']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->sort('category:desc')
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->json('hits');

        $this->assertEquals('c', $hits[0]['_source']['category']);
        $this->assertEquals('b', $hits[1]['_source']['category']);
        $this->assertEquals('a', $hits[2]['_source']['category']);
    }

    /**
     * @test
     */
    public function filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->bool('active');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['active' => true]),
            new Document(['active' => false]),
            new Document(['active' => true]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->filters('active:true')
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function size(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->bool('active');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['active' => true]),
            new Document(['active' => false]),
            new Document(['active' => true]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->size(2)
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function query(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->text('description');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Narnia',
                'description' => 'Awesome',
            ]),
            new Document([
                'name' => 'Disneyland',
                'description' => 'Too Good',
            ]),
            new Document([
                'name' => 'Neverland',
                'description' => 'Good',
            ]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('Good')
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function matches_all_on_empty_string(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->text('description');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Narnia',
                'description' => 'Awesome',
            ]),
            new Document([
                'name' => 'Disneyland',
                'description' => 'Too Good',
            ]),
            new Document([
                'name' => 'Neverland',
                'description' => 'Good',
            ]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(3, $hits);
    }

    /**
     * @test
     */
    public function search_test(): void
    {
        $indexName = uniqid();
        $properties = new NewProperties;
        $properties->text('name');
        $properties->text('description');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($properties)
            ->lowercase()
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey',
                'description' => 'Adventure in the woods',
            ]),
            new Document([
                'name' => 'Goofy',
                'description' => 'Mickey and his friends',
            ]),
            new Document([
                'name' => 'Donald',
                'description' => 'Chasing Goofy',
            ]),
        ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($properties)
            ->queryString('mickey')
            ->fields(['name'])
            ->retrieve(['name', 'description'])
            ->get()
            ->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Goofy']),
            new Document(['name' => 'Donald']),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Mickey', weight: 2)
            ->queryString('Goofy', weight: 1)
            ->get();

        $hits = $res->hits();

        $this->assertEquals('Mickey', $hits[0]['name']);
        $this->assertEquals(2, $res->total());
    }

    /**
     * @test
     */
    public function no_keyword_search_flag(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->title('name')->semantic(dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'name' => ['King', 'Prince'],
                    'age' => 10,
                ]),
                new Document([
                    'name' => 'Queen',
                    'age' => 20,
                ]),
            ]);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->noResultsOnEmptySearch()
            ->disableKeywordSearch()
            ->properties($blueprint)
            ->queryString('Woman')
            ->get();

        $this->assertEquals(0, $response->total());
    }

    /**
     * @test
     */
    public function direct_query_string_vector_searches_elasticsearch_semantic_field(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Alpha Manual'], _id: 'alpha'),
                new Document(['title' => 'Beta Handbook'], _id: 'beta'),
            ]);

        $vector = $this->embeddingApi->embed('Alpha Manual', 384);

        $search = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('ignored text', fields: ['title'])
            ->size(1);

        $queryString = $search->queryStrings()[0];
        $queryString->setDimension(384)->setVector($vector);

        $hits = $search->hits();

        $this->assertSame('alpha', $hits[0]->_id);
        $this->assertSame('ignored text', (string) $queryString);
        $this->assertTrue($queryString->hasFields());
        $this->assertSame([
            'text' => 'ignored text',
            'weight' => 1.0,
            'dimension' => 384,
            'vector' => $vector,
            'fields' => ['title'],
        ], $queryString->toArray());
    }

    /**
     * @test
     */
    public function semantic_search_scoped_to_non_vector_field_returns_sorted_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->keyword()->makeSortable();
        $blueprint->text('description')->semantic(dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'title' => 'Beta',
                    'description' => 'Second document',
                ], _id: 'beta'),
                new Document([
                    'title' => 'Alpha',
                    'description' => 'First document',
                ], _id: 'alpha'),
            ]);

        $hits = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->queryString('Alpha', fields: ['title'])
            ->sort('title:asc')
            ->size(2)
            ->get()
            ->json('hits');

        $this->assertSame(['alpha'], array_map(
            fn (array $hit): string => $hit['_id'],
            $hits
        ));
    }

    /**
     * @test
     */
    public function boost_field_search_test(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->boost();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'boost' => 4,
            ]),
            new Document([
                'boost' => 10,
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->get();

        $hits = $res->json('hits');

        $this->assertEquals(10, $hits[0]['_source']['boost']);
    }

    /**
     * @test
     */
    public function no_boost_field_search_test(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->number('boost');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'boost' => 4,
            ]),
            new Document([
                'boost' => 10,
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->get();

        $hits = $res->json('hits');

        $this->assertEquals(4, $hits[0]['_source']['boost']);
    }

    /**
     * @test
     */
    public function multi_lang_search(): void
    {
        $deIndexName = uniqid('de');
        $enIndexName = uniqid('en');

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($deIndexName)
            ->properties($blueprint)
            ->language(new German)
            // without normalize it's not found
            ->germanNormalize()
            ->create();

        $this->sigmie->newIndex($enIndexName)
            ->properties($blueprint)
            ->language(new English)
            ->create();

        $deDocs = [
            new Document([
                'name' => 'tür',
            ]),
        ];

        $enDocs = [
            new Document([
                'name' => 'door',
            ]),
        ];

        $this->sigmie->collect($deIndexName, refresh: true)->merge($deDocs);
        $this->sigmie->collect($enIndexName, refresh: true)->merge($enDocs);

        $res = $this->sigmie->newSearch(sprintf('%s,%s', $deIndexName, $enIndexName))
            ->properties($blueprint)
            ->queryString('door tur')
            ->get();

        $this->assertEquals(2, $res->total());
    }

    /**
     * @test
     */
    public function range_query(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->range('numbers')->integer()
            ->withQueries(function (string $queryString): array {
                $bound = filter_var($queryString, FILTER_VALIDATE_INT);
                if ($bound === false) {
                    return [];
                }

                return [new Range('numbers', ['>=' => $bound, '<=' => $bound])];
            });

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'numbers' => ['gt' => 10, 'lt' => 20],
                ]),
                new Document([
                    'numbers' => ['gt' => 21, 'lt' => 31],
                ]),
            ]);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('lorem')
            ->get();

        $this->assertEquals(0, $response->total());

        $response = $this->sigmie
            ->newSearch($indexName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('15')
            ->get();

        $this->assertEquals(1, $response->total());

        $response = $this->sigmie
            ->newSearch($indexName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('9')
            ->get();

        $this->assertEquals(0, $response->total());
    }

    /**
     * @test
     */
    public function response_code_is_populated(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->merge([
                new Document(['name' => 'Test Document']),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Test')
            ->get();

        $this->assertEquals(200, $response->code());
    }

    /**
     * @test
     */
    public function lazy_returns_generator(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $gen = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->lazy();

        $this->assertInstanceOf(Generator::class, $gen);
    }

    /**
     * @test
     */
    public function lazy_iterates_all_documents_across_pages(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $docs = [];
        for ($i = 0; $i < 5; $i++) {
            $docs[] = new Document(['name' => 'item'.$i]);
        }

        $this->sigmie->collect($indexName, refresh: true)->merge($docs);

        $hits = iterator_to_array(
            $this->sigmie->newSearch($indexName)
                ->properties($blueprint)
                ->chunk(2)
                ->lazy()
        );

        $this->assertCount(5, $hits);
        foreach ($hits as $hit) {
            $this->assertInstanceOf(Hit::class, $hit);
        }
    }

    /** @test */
    public function lazy_scans_include_the_same_document_id_from_each_index(): void
    {
        $indices = [uniqid('scan-a-'), uniqid('scan-b-')];
        $properties = new NewProperties;
        $properties->text('name');

        foreach ($indices as $name) {
            $this->sigmie->newIndex($name)->properties($properties)->create();
            $this->sigmie->collect($name, refresh: true)->merge([
                new Document(['name' => $name], _id: 'shared-id'),
            ]);
        }

        $hits = iterator_to_array($this->sigmie->newSearch(implode(',', $indices))
            ->properties($properties)
            ->chunk(1)
            ->lazy());

        $this->assertEqualsCanonicalizing($indices, array_map(fn (Hit $hit): string => $hit->_source['name'], $hits));
        $this->assertSame(['shared-id', 'shared-id'], array_column($hits, '_id'));
    }

    /**
     * @test
     */
    public function each_respects_filters_for_lazy_iteration(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->bool('active');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['active' => true]),
            new Document(['active' => false]),
            new Document(['active' => true]),
            new Document(['active' => true]),
        ]);

        $ids = [];

        $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->filters('active:true')
            ->chunk(2)
            ->each(function (Hit $hit) use (&$ids): void {
                $this->assertInstanceOf(Hit::class, $hit);
                $ids[] = $hit->_id;
            });

        $this->assertCount(3, $ids);
    }

    /**
     * @test
     */
    public function each_respects_query_string_for_lazy_iteration(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Goofy']),
            new Document(['name' => 'Mickey Mouse']),
        ]);

        $names = [];

        $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Mickey')
            ->each(function (Hit $hit) use (&$names): void {
                $names[] = $hit->_source['name'];
            });

        $this->assertCount(2, $names);
        $this->assertContains('Mickey', $names);
        $this->assertContains('Mickey Mouse', $names);
    }

    /**
     * @test
     */
    public function unique_by_collapses_results_by_field(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('product_id');
        $blueprint->integer('ord');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['product_id' => 'A', 'ord' => 1]),
            new Document(['product_id' => 'A', 'ord' => 2]),
            new Document(['product_id' => 'B', 'ord' => 3]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->sort('ord:asc')
            ->uniqueBy('product_id')
            ->get();

        $hits = $res->json('hits');

        $this->assertCount(2, $hits);
    }

    public function test_collapsed_scan_sorted_by_another_field_keeps_rank_and_facet_filter(): void
    {
        $name = uniqid('collapsed_rank_');
        $properties = new NewProperties;
        $properties->keyword('group');
        $properties->keyword('status');
        $properties->integer('rank');

        $index = $this->sigmie->newIndex($name)->properties($properties)->create();
        try {
            $this->sigmie->collect($name, refresh: true)->merge([
                new Document(['group' => 'B', 'rank' => 1, 'status' => 'excluded'], _id: 'excluded'),
                new Document(['group' => 'A', 'rank' => 2, 'status' => 'active'], _id: 'best-a'),
                new Document(['rank' => 3, 'status' => 'active'], _id: 'best-missing'),
                new Document(['group' => 'A', 'rank' => 4, 'status' => 'active'], _id: 'other-a'),
                new Document(['rank' => 5, 'status' => 'active'], _id: 'other-missing'),
                new Document(['group' => 'C', 'rank' => 6, 'status' => 'active'], _id: 'best-c'),
                new Document(['group' => 'B', 'rank' => 7, 'status' => 'active'], _id: 'best-b'),
            ]);

            $search = $this->sigmie->newSearch($name)->properties($properties)
                ->uniqueBy('group', top: 1)->sort('rank:asc')->facets('status', 'status:active')
                ->retrieve(['rank'])->chunk(2);

            $hits = iterator_to_array($search->lazy());

            $this->assertSame(['best-a', 'best-missing', 'best-c', 'best-b'], array_column($hits, '_id'));
            $this->assertSame('other-a', $hits[0]->toArray()['inner_hits']['top']['hits']['hits'][0]['_id']);
        } finally {
            $index->delete();
        }
    }

    /** @dataProvider collapsedScanGroups */
    public function test_collapsed_scan_reads_multiple_pages_in_collapse_order(string $type, array $values, array $expected, string $order = 'desc'): void
    {
        $name = uniqid('collapsed_scan_');
        $blueprint = new NewProperties;
        $blueprint->{$type}('group');

        $index = $this->sigmie->newIndex($name)->properties($blueprint)->create();
        try {
            $this->sigmie->collect($name, refresh: true)->merge([
                ...array_map(fn (int|string $group): Document => new Document(['group' => $group]), $values),
                new Document(['name' => 'ungrouped']),
            ]);

            $search = $this->sigmie->newSearch($name)->properties($blueprint)
                ->queryString('')->uniqueBy('group')->sort('group:'.$order)->chunk(1);

            $groups = [];
            foreach ($search->lazy() as $hit) {
                $groups[] = $hit->_source['group'] ?? null;
            }

            $this->assertSame($expected, $groups);
        } finally {
            $index->delete();
        }
    }

    public static function collapsedScanGroups(): array
    {
        return [
            ['integer', [1, 2, 2, 3], [3, 2, 1, null]],
            ['keyword', ['A', 'B', 'B', 'C'], ['C', 'B', 'A', null]],
            ['keyword', ['A', 'B', 'B', 'C'], ['A', 'B', 'C', null], 'asc'],
        ];
    }

    public function test_html_highlight_closes_before_the_containing_tag(): void
    {
        $name = uniqid('html_highlight_');
        $blueprint = new NewProperties;
        $blueprint->html('body');

        $index = $this->sigmie->newIndex($name)->properties($blueprint)->create();
        try {
            $this->sigmie->collect($name, refresh: true)->merge([new Document(['body' => '<b>Bonjour</b>'])]);

            $hits = $this->sigmie->newSearch($name)->properties($blueprint)
                ->fields(['body'])->queryString('Bonjour')->highlighting(['body'], '<em>', '</em>')->get()->json('hits');

            $this->assertSame(['<b><em>Bonjour</em></b>'], $hits[0]['highlight']['body']);
        } finally {
            $index->delete();
        }
    }

    /** @test */
    public function collapsed_inner_hits_respect_the_search_minimum_score(): void
    {
        $name = uniqid('collapsed_score_');
        $blueprint = new NewProperties;
        $blueprint->keyword('group');
        $blueprint->boost('priority');
        $blueprint->integer('ord');

        $this->sigmie->newIndex($name)->properties($blueprint)->create();
        $this->sigmie->collect($name, refresh: true)->merge([
            new Document(['group' => 'A', 'priority' => 10, 'ord' => 1], _id: 'representative'),
            new Document(['group' => 'A', 'priority' => 1, 'ord' => 2], _id: 'below-threshold'),
            new Document(['group' => 'A', 'priority' => 8, 'ord' => 3], _id: 'eligible'),
        ]);

        $hits = $this->sigmie->newSearch($name)
            ->properties($blueprint)
            ->queryString('')
            ->minScore(5)
            ->sort('ord:asc')
            ->uniqueBy('group', top: 3)
            ->get()
            ->json('hits');

        $this->assertSame('representative', $hits[0]['_id']);
        $innerIds = array_column($hits[0]['inner_hits']['top']['hits']['hits'], '_id');
        $this->assertNotContains('below-threshold', $innerIds);
        $this->assertSame(['eligible'], $innerIds);
    }

    /**
     * @test
     */
    public function unique_by_with_top_returns_inner_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('product_id');
        $blueprint->integer('ord');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['product_id' => 'A', 'ord' => 1]),
            new Document(['product_id' => 'A', 'ord' => 2]),
            new Document(['product_id' => 'A', 'ord' => 3]),
            new Document(['product_id' => 'B', 'ord' => 0]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->sort('ord:asc')
            ->uniqueBy('product_id', top: 2)
            ->get();

        $hits = $res->json('hits');
        $this->assertCount(2, $hits);

        $groupA = null;
        foreach ($hits as $hit) {
            if ($hit['_source']['product_id'] === 'A') {
                $groupA = $hit;
                break;
            }
        }

        $this->assertNotNull($groupA);
        $this->assertArrayHasKey('inner_hits', $groupA);
        $this->assertArrayHasKey('top', $groupA['inner_hits']);
        $this->assertSame(
            [2, 3],
            array_column(array_column($groupA['inner_hits']['top']['hits']['hits'], '_source'), 'ord'),
        );
        $this->assertSame([], $hits[0]['inner_hits']['top']['hits']['hits']);
    }

    /**
     * @test
     */
    public function filter_query_ands_a_hard_clause_with_the_string_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->name('name');
        $blueprint->keyword('category');
        $blueprint->bool('active');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Alice', 'category' => 'public', 'active' => true]),
            new Document(['name' => 'Bob', 'category' => 'public', 'active' => false]),
            new Document(['name' => 'Carol', 'category' => 'private', 'active' => true]),
        ]);

        // Baseline: the OR string filter alone matches all three documents.
        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->filters("category:'public' OR category:'private'")
            ->get();

        $this->assertEquals(3, $res->total());

        // The hard clause is ANDed on top without corrupting the OR (should)
        // semantics: only the active documents from either category survive.
        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->filters("category:'public' OR category:'private'")
            ->filterQuery(new Term('active', true))
            ->get();

        $names = array_map(fn (array $hit): string => $hit['_source']['name'], $res->json('hits'));

        sort($names);

        $this->assertEquals(2, $res->total());
        $this->assertEquals(['Alice', 'Carol'], $names);
    }

    /**
     * @test
     */
    public function filter_query_constrains_facet_counts(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->name('name');
        $blueprint->keyword('category');
        $blueprint->bool('active');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Alice', 'category' => 'public', 'active' => true]),
            new Document(['name' => 'Bob', 'category' => 'public', 'active' => false]),
            new Document(['name' => 'Carol', 'category' => 'private', 'active' => true]),
        ]);

        // The hard clause lives in the query, so the facet aggregation is
        // scoped by it too: the inactive 'public' document is not counted.
        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('category')
            ->filterQuery(new Term('active', true))
            ->get();

        $facets = $res->facet('category');

        $this->assertEquals(['private' => 1, 'public' => 1], $facets);
    }

    /**
     * @test
     */
    public function filters_throw_on_error_can_be_opted_into(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('category');

        $this->expectException(ParseException::class);

        $this->sigmie->newSearch('test')
            ->properties($blueprint)
            ->filters("nonexistent:'x'", throwOnError: true);
    }

    /**
     * @test
     */
    public function filters_collect_errors_by_default_instead_of_throwing(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('category');

        $search = $this->sigmie->newSearch('test')
            ->properties($blueprint)
            ->filters("nonexistent:'x'");

        $this->assertNotEmpty($search->filterParser->errors());
    }

    /**
     * @test
     */
    public function new_search_returns_custom_aggregations_from_real_index(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');
        $blueprint->bool('active');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['category' => 'public', 'active' => true]),
                new Document(['category' => 'public', 'active' => false]),
                new Document(['category' => 'private', 'active' => true]),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->filterQuery(new Term('active', true))
            ->aggregate(fn (Aggs $aggs): Composite => $aggs->composite(
                'categories',
                [
                    [
                        'category' => [
                            'terms' => [
                                'field' => 'category',
                            ],
                        ],
                    ],
                ],
                10,
            ))
            ->get();

        $buckets = $response->aggregation('categories.buckets');

        $this->assertIsArray($buckets);
        $this->assertEquals(['private', 'public'], array_map(
            fn (array $bucket): string => $bucket['key']['category'],
            $buckets,
        ));
        $this->assertEquals([1, 1], array_map(
            fn (array $bucket): int => $bucket['doc_count'],
            $buckets,
        ));
        $this->assertEquals(2, $response->total());
    }

    /** @test */
    public function input_parser_preserves_keywords_inside_text_and_quoted_values(): void
    {
        $parsed = (new InputParser)->parse(<<<'INPUT'
            RESORT GUIDE FILTER title:"Keep the FILTER and the SORT order \"as is\"" SORT rank:desc
            INPUT);

        $this->assertSame('RESORT GUIDE', trim($parsed['query_string']));
        $this->assertSame(<<<'FILTER'
            title:"Keep the FILTER and the SORT order \"as is\""
            FILTER, $parsed['filter_string']);
        $this->assertSame('rank:desc', $parsed['sort_string']);

        $parsed = (new InputParser)->parse("O'Reilly FILTER title:'Guide'");

        $this->assertSame("O'Reilly", trim($parsed['query_string']));
        $this->assertSame("title:'Guide'", $parsed['filter_string']);
    }

    /**
     * @test
     */
    public function input_parser_parts_drive_elasticsearch_search(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->category('category');
        $blueprint->number('rank');

        $this->sigmie->newIndex($indexName)
            ->lowercase()
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Beta Public Guide', 'category' => 'public', 'rank' => 2]),
                new Document(['title' => 'Beta Private Guide', 'category' => 'private', 'rank' => 1]),
                new Document(['title' => 'Alpha Public Guide', 'category' => 'public', 'rank' => 3]),
            ]);

        $parsed = (new InputParser)->parse("Beta FILTER category:'public' SORT rank:desc");

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString($parsed['query_string'])
            ->filters($parsed['filter_string'])
            ->sort($parsed['sort_string'])
            ->get();

        $this->assertSame(1, $response->total());
        $this->assertSame('Beta Public Guide', $response->hits()[0]->_source['title']);
    }

    /**
     * @test
     */
    public function facets_throw_on_error_can_be_opted_into(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('category');

        $this->expectException(ParseException::class);

        $this->sigmie->newSearch('test')
            ->properties($blueprint)
            ->facets('category', "nonexistent:'x'", throwOnError: true);
    }

    /**
     * @test
     */
    public function new_search_without_properties_guard_paths_are_backed_by_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->add(new Document(['title' => 'New search guard coverage'], _id: 'matching'));

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('guard')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $search = new class($this->elasticsearchConnection) extends NewSearch
        {
            public function hasSemanticFieldCoverage(): bool
            {
                return $this->hasSemanticFields();
            }

            public function populateVectorPoolCoverage(): void
            {
                $this->populateVectorPool();
            }

            public function vectorFieldsCoverage(): Collection
            {
                return $this->getVectorFields();
            }

            public function requiredApisCoverage(): array
            {
                return $this->getRequiredEmbeddingApis();
            }
        };

        $this->assertFalse($search->hasSemanticFieldCoverage());
        $this->assertSame([], $search->vectorFieldsCoverage()->toArray());
        $this->assertSame([], $search->requiredApisCoverage());

        $searchWithRequiredApis = new class($this->elasticsearchConnection) extends NewSearch
        {
            public function populateVectorPoolCoverage(): void
            {
                $this->populateVectorPool();
            }

            protected function getRequiredEmbeddingApis(): array
            {
                return ['missing-api'];
            }
        };

        $searchWithRequiredApis->populateVectorPoolCoverage();
    }

    /**
     * @test
     */
    public function hit_serialization_paths_are_backed_by_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->add(new Document(['title' => 'Hit serialization'], _id: 'matching'));

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('serialization')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $hit = new Hit(['title' => 'Hit serialization'], 'matching', 1.5, $indexName);
        $reranked = new RerankedHit($hit, 0.9);

        $this->assertSame([
            '_index' => $indexName,
            '_id' => 'matching',
            '_source' => ['title' => 'Hit serialization'],
            '_score' => 1.5,
        ], $hit->toArray());
        $this->assertSame([
            '_index' => $indexName,
            '_id' => 'matching',
            '_source' => ['title' => 'Hit serialization'],
            '_score' => 1.5,
            '_rerank_score' => 0.9,
        ], $reranked->toArray());
    }

}

