<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Http\Promise\Promise;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Sigmie\AI\APIs\VoyageEmbeddingsApi;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Analytics\Analytics;
use Sigmie\Base\Contracts\ElasticsearchConnection as ElasticsearchConnectionInterface;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse as ElasticsearchResponseContract;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\Drivers\Opensearch;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Base\Http\ElasticsearchResponse as HttpElasticsearchResponse;
use Sigmie\Base\Http\Responses\Search as HttpSearch;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Document\RerankedHit;
use Sigmie\Http\Contracts\JSONClient;
use Sigmie\Http\Contracts\JSONRequest;
use Sigmie\Http\JSONResponse;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\ElasticsearchNestedVector;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\Id;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Name;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\InputParser;
use Sigmie\Parse\ParseException;
use Sigmie\Parse\RangeOperatorParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Aggregations\Metrics\Max;
use Sigmie\Query\Aggs;
use Sigmie\Query\NewQuery;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Search;
use Sigmie\Query\Suggest;
use Sigmie\Search\Autocomplete\NewPipeline;
use Sigmie\Search\Autocomplete\Pipeline;
use Sigmie\Search\Autocomplete\Set;
use Sigmie\Search\Contracts\MultiSearchable;
use Sigmie\Search\Contracts\ResponseFormater;
use Sigmie\Search\ExistingScript;
use Sigmie\Search\Formatters\AbstractFormatter;
use Sigmie\Search\Formatters\RawElasticsearchFormat;
use Sigmie\Search\Formatters\SigmieMultiSearchResponse;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Search\InnerHits;
use Sigmie\Search\MMR;
use Sigmie\Search\NewContextComposer;
use Sigmie\Search\NewMultiSearch;
use Sigmie\Search\NewRerank;
use Sigmie\Search\NewSearch;
use Sigmie\Search\NewTemplate;
use Sigmie\Search\RawQuery;
use Sigmie\Search\RRF;
use Sigmie\Search\SearchContext;
use Sigmie\Search\SearchTemplate;
use Sigmie\Search\VectorPool;
use Sigmie\Sigmie;
use Sigmie\SigmieIndex;
use Sigmie\Testing\FakeEmbeddingsApi;
use Sigmie\Testing\FakeRerankApi;
use stdClass;
use Symfony\Component\Yaml\Yaml;

use function Sigmie\Functions\object_map;

class SearchCorrectnessTest extends TestCase
{
    public function test_built_search_parses_filters_with_its_mappings(): void
    {
        $props = new NewProperties;
        $props->category('brand');

        $raw = $this->search($props)->makeSearch()->postFilterString('brand:acme')->toRaw();

        $this->assertSame('acme', $raw['post_filter']['bool']['must'][0]['term']['brand.keyword']['value']);
    }

    public function test_built_search_can_replace_the_parsed_sort(): void
    {
        $props = new NewProperties;
        $props->number('rank');

        $raw = $this->search($props)->sort('rank:desc')->makeSearch()->sortString('_doc')->toRaw();

        $this->assertSame(['_doc'], $raw['sort']);
    }

    public function test_adding_an_aggregation_to_a_built_search_keeps_its_facets(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');
        $props->number('price');

        $raw = $this->search($props)->facets('brand')->makeSearch()
            ->aggregate(fn (Aggs $aggs): Max => $aggs->max('highest', 'price'))->toRaw();

        $this->assertArrayHasKey('brand', $raw['aggs']);
        $this->assertArrayHasKey('highest', $raw['aggs']);
    }

    public function test_facet_ignores_its_grouped_predicates_in_negations_and_alternatives(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');
        $props->keyword('color');

        $parser = new FilterParser($props);
        $brand = $props->get()->get('brand');

        $raw = $parser->facetFilter($brand, 'NOT (brand:acme) AND color:red')->toRaw();
        $this->assertStringNotContainsString('must_not', json_encode($raw));
        $raw = $parser->facetFilter($brand, 'brand:acme OR color:red')->toRaw();
        $this->assertStringNotContainsString('match_all', json_encode($raw));
        $this->assertStringContainsString('"red"', json_encode($raw));
    }

    public function test_nested_facet_selections_use_the_child_fields_logic(): void
    {
        $props = new NewProperties;
        $props->nested('items', fn (NewProperties $items): Keyword => $items->keyword('color')->facetDisjunctive());

        $this->assertSame('(items:{color:red} OR items:{color:blue})',
            (new FacetParser($props))->parseFilterString('items:{color:red} items:{color:blue}'));
    }

    public function test_name_primary_match_uses_the_restored_search_analyzer(): void
    {
        $field = Name::fromRaw(['author' => [
            'type' => 'text', 'analyzer' => 'standard', 'search_analyzer' => 'whitespace',
        ]]);

        $this->assertSame('whitespace', $field->queries('Ada')[0]->toRaw()['match']['author']['analyzer']);
    }

    public function test_id_exact_filters_use_the_types_filter_field(): void
    {
        $props = new NewProperties;
        $props->type(new class('sku') extends Id
        {
            public function filterableName(): string
            {
                return $this->sortableName();
            }
        });

        $raw = (new FilterParser($props))->parse('sku:5')->toRaw();
        $this->assertArrayHasKey('sku.sortable', $raw['bool']['must'][0]['term']);
    }

    public function test_wildcard_keeps_literal_directory_separators(): void
    {
        $props = new NewProperties;
        $props->keyword('path');

        $raw = (new FilterParser($props))->parse('path:"C:\\Users\\Ada*"')->toRaw();
        $this->assertSame('C:\\\\Users\\\\Ada*', $raw['bool']['must'][0]['wildcard']['path']['value']);
    }

    public function test_weighting_a_field_keeps_the_custom_clause_boost(): void
    {
        $props = new NewProperties;
        $term = (new Term('sku', 'book'))->boost(3);
        $props->keyword('sku')->withQueries(fn (): array => [$term]);
        $search = $this->search($props)->queryString('book');
        $this->assertStringContainsString('"value":"book","boost":3', json_encode($search->makeSearch()->toRaw()));
        $weighted = $search->weight(['sku' => 4])->makeSearch()->toRaw();
        $this->assertStringContainsString('"must":[{"term":{"sku":{"value":"book","boost":3}}}],"boost":4', json_encode($weighted));
        $this->assertSame(3.0, $term->toRaw()['term']['sku']['boost']);
    }

    public function test_declared_fields_survive_assigning_properties(): void
    {
        $props = new NewProperties;
        $props->text('title');
        $props->text('notes');

        $search = (new NewSearch($this->connection()))->index('products')->fields(['title'])->properties($props)->queryString('book');
        $query = json_encode($search->makeSearch()->toRaw()['query']);

        $this->assertStringContainsString('"title"', $query);
        $this->assertStringNotContainsString('"notes"', $query);
    }

    public function test_named_properties_can_search_their_default_fields(): void
    {
        $props = new NewProperties;
        $props->text('title');

        $search = (new NewSearch($this->connection()))->index('products')->properties($props->get(name: 'product'));
        $query = $search->queryString('book')->makeSearch()->toRaw()['query'];

        $this->assertStringContainsString('"product.title"', json_encode($query));
    }

    public function test_highlighting_a_relative_name_keeps_its_prefix_fields(): void
    {
        $props = new NewProperties;
        $props->searchAsYouType('title');

        $search = (new NewSearch($this->connection()))->index('products')->properties($props->get(name: 'product'));
        $raw = $search->highlighting(['title'], '<b>', '</b>')->makeSearch()->toRaw();

        $this->assertSame(['product.title._index_prefix'], $raw['highlight']['fields']['product.title']['matched_fields'] ?? null);
    }

    public function test_name_queries_target_only_mapped_prefix_fields(): void
    {
        $field = Name::fromRaw(['author' => ['type' => 'text', 'analyzer' => 'standard']]);

        $this->assertSame(['author'], $field->names());
        $this->assertCount(1, $field->queries('Ada'));
    }

    public function test_custom_fuzzy_clause_keeps_the_result_of_its_setter(): void
    {
        $clause = new class('title', 'book') extends Match_
        {
            public function fuzziness(?string $fuzziness): static
            {
                $query = clone $this;
                $query->fuzziness = $fuzziness;

                return $query;
            }
        };
        $props = new NewProperties;
        $props->text('title')->withQueries(fn (): array => [$clause]);
        $search = $this->search($props)->typoTolerantAttributes(['title'])->queryString('book');

        $this->assertStringContainsString('"fuzziness":"AUTO:3,6"', json_encode($search->makeSearch()->toRaw()['query']));
    }

    public function test_restored_name_prefix_without_an_analyzer_uses_the_default(): void
    {
        $field = Name::fromRaw(['author' => [
            'type' => 'text', 'fields' => ['author_text' => ['type' => 'text']],
        ]]);

        $query = $field->queries('Ada')[1]->toRaw();
        $this->assertSame('default', $query['match_phrase_prefix']['author.author_text']['analyzer']);
    }

    public function test_formatted_hit_keeps_source_and_field_object_keys(): void
    {
        $hit = ['_source' => ['0' => 'leaf'], 'fields' => ['0' => ['leaf']]];
        $response = $this->search(new NewProperties)->formatResponses(['hits' => ['hits' => [$hit]]]);

        $this->assertSame('{"0":"leaf"}', json_encode($response->json('hits.0._source')));
        $this->assertSame('{"0":["leaf"]}', json_encode($response->json('hits.0.fields')));
    }

    public function test_template_preserves_sparse_highlight_attribute_lists(): void
    {
        $props = new NewProperties;
        $props->text('title');

        $source = (new NewTemplate($this->connection()))->properties($props)->id('books')
            ->highlighting([2 => 'title'], '<mark>', '</mark>')->get()->source();

        $this->assertStringContainsString('"pre_tags":["<mark>"]', $source);
    }

    public function test_mapping_keeps_explicit_search_fields(): void
    {
        $props = new NewProperties;
        $props->text('title');
        $props->text('description');

        $search = (new NewSearch($this->connection()))->index('books')->fields(['title'])
            ->properties($props)->queryString('atlas');

        $this->assertStringNotContainsString('description', json_encode($search->makeSearch()->toRaw()['query']));
    }

    public function test_image_without_a_searchable_vector_matches_nothing(): void
    {
        $props = new NewProperties;
        $props->text('title');

        $body = $this->search($props)->semantic()->queryImage('cover.jpg')->makeSearch()->toRaw();

        $this->assertStringContainsString('match_none', json_encode($body['query']));
    }

    public function test_embedding_requests_respect_the_query_dimension(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'small', dimensions: 128);
        $props->text('description')->semantic(api: 'large', dimensions: 256);
        $excluded = $this->createMock(EmbeddingsApi::class);
        $excluded->method('maxBatchSize')->willReturn(32);
        $excluded->expects($this->never())->method('batchEmbed');

        $body = $this->search($props)->semantic()->apis(['large' => $excluded, 'small' => new FakeEmbeddingsApi($this->createStub(EmbeddingsApi::class), 32)])
            ->queryString('atlas', dimension: 128)->makeSearch()->toRaw();

        $this->assertCount(1, $body['knn']);
    }

    public function test_collapsed_hits_format_their_requested_nested_matches(): void
    {
        $props = new NewProperties;
        $props->keyword('group');
        $props->nested('reviews', fn (NewProperties $reviews): Text => $reviews->text('comment'));

        $search = $this->search($props)->fields(['reviews.comment'])->queryString('battery')
            ->innerHits('reviews')->uniqueBy('group', top: 1);
        $search->makeSearch();

        $nested = ['inner_hits' => ['reviews#1' => ['hits' => ['total' => 1, 'hits' => [
            ['_nested' => ['field' => 'reviews', 'offset' => 2], '_source' => ['comment' => 'battery']],
        ]]]]];
        $hit = ['inner_hits' => ['top' => ['hits' => ['hits' => [$nested]]]]];
        $response = $search->formatResponses(['hits' => ['hits' => [$hit]]]);

        $this->assertSame('battery', $response->json('hits.0.inner_hits.top.hits.hits.0._matches.reviews.items.0.comment'));
    }

    public function test_group_expansion_receives_the_vector_query(): void
    {
        $props = new NewProperties;
        $props->keyword('group');
        $props->text('title')->semantic(api: 'embedding', dimensions: 128);
        $body = $this->search($props)->semantic()->disableKeywordSearch()
            ->queryString('atlas', vector: array_fill(0, 128, 0.1))->size(1)
            ->uniqueBy('group', top: 2)->makeSearch()->toRaw();

        $this->assertArrayNotHasKey('knn', $body);
        $this->assertGreaterThanOrEqual(3, $body['query']['bool']['must'][1]['bool']['should'][1]['knn']['k']);
    }

    public function test_context_reads_hits_from_a_raw_multi_search(): void
    {
        $multi = new NewMultiSearch($this->respondingConnection(['responses' => [
            ['hits' => ['hits' => [['_id' => '1', '_source' => ['title' => 'Atlas', 'year' => 2020]]]]],
        ]]));
        $multi->raw('books', ['size' => 1]);

        $this->assertSame('{"title":"Atlas"}', (new NewContextComposer)->fields(['title'])->compose($multi->hits()));
    }

    public function test_formatted_reranked_hits_expose_the_rerank_score(): void
    {
        $raw = ['_id' => '1', '_score' => 2.0, '_rerank_score' => 0.75];
        $response = $this->search(new NewProperties)->formatResponses(['hits' => ['hits' => [$raw]]]);

        $this->assertInstanceOf(RerankedHit::class, $response->hits()[0]);
        $this->assertSame(0.75, $response->hits()[0]->_rerank_score);
    }

    public function test_template_with_no_facets_emits_an_aggregation_object(): void
    {
        $source = (new NewTemplate($this->connection()))->id('books')->facets('')->get()->source();

        $this->assertStringContainsString('{{^facets}}{}{{/facets}}', $source);
    }

    public function test_template_filters_vector_candidates_before_ranking(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'embedding', dimensions: 128);
        $props->keyword('category');
        $source = (new NewTemplate($this->connection()))->id('books')->properties($props)
            ->semantic()->filters('category:atlas')->get()->source();

        [, $vectorQuery] = explode('"knn":', $source, 2);
        [$candidates] = explode('"num_candidates":', $vectorQuery, 2);
        $this->assertStringContainsString('"category":{"value":"atlas"', $candidates);
    }

    public function test_template_text_embedding_fallback_uses_the_mapped_query(): void
    {
        $props = new NewProperties;
        $title = $props->text('title');
        $title->semantic(api: 'embedding', dimensions: 128);
        $title->withQueries(fn (string $query): array => [new Term('title.raw', $query)]);

        $source = (new NewTemplate($this->connection()))->id('books')->properties($props)->semantic()->get()->source();

        $this->assertStringContainsString('"term":{"title.raw":{"value":"{{_embeddings_title}}"', $source);
    }

    public function test_formatting_a_partial_facet_response_rejects_the_counts(): void
    {
        $props = new NewProperties;
        $props->keyword('category');

        $partial = ['timed_out' => true, 'aggregations' => ['category' => [
            'category' => ['buckets' => [['key' => 'atlas', 'doc_count' => 1]]],
        ]]];

        $this->expectException(ElasticsearchException::class);
        $this->search($props)->facets('category')->formatRespones(['hits' => ['hits' => []]], $partial)->facet('category');
    }

    public function test_template_keeps_literal_highlight_delimiters(): void
    {
        $props = new NewProperties;
        $props->text('title');

        $source = (new NewTemplate($this->connection()))->id('books')->properties($props)
            ->highlighting(['title'], '{{', '}}')->get()->source();

        $this->assertStringContainsString('"pre_tags":["\u007b\u007b"]', $source);
    }

    public function test_caller_vector_is_normalized_for_dot_product_search(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'embedding', dimensions: 2)->dotProductSimilarity();
        $body = $this->search($props)->semantic()->queryString('atlas', vector: [3.0, 4.0])->makeSearch()->toRaw();

        $this->assertSame([0.6, 0.8], $body['knn'][0]['query_vector']);
    }

    public function test_filters_resolve_the_mapped_timestamp_field(): void
    {
        $props = new NewProperties;
        $props->datetime('@timestamp');

        $query = (new FilterParser($props))->parse('@timestamp>=1704067200000')->toRaw();

        $this->assertSame(1704067200000, $query['bool']['must'][0]['range']['@timestamp']['gte']);
        $this->assertSame('(@timestamp>=1704067200000)', (new FacetParser($props))->parseFilterString('@timestamp>=1704067200000'));
    }

    public function test_geo_sort_preserves_points_named_like_options(): void
    {
        $props = new NewProperties;
        $props->geoPoint('order');
        $props->geoPoint('unit');

        $parser = new SortParser($props, false);
        $sort = $parser->parse('order[51,13]:km:asc unit[51,13]:m:desc');
        $point = ['lat' => 51.0, 'lon' => 13.0];

        $this->assertSame(['order' => $point, 'unit' => 'km'], $sort[0]['_geo_distance']);
        $this->assertSame(['unit' => $point, 'order' => 'desc'], $sort[1]['_geo_distance']);
        $colliding = $parser->parse('order[51,13]:km:desc unit[51,13]:km:asc');
        $this->assertSame('desc', $colliding[0]['_script']['order']);
        $this->assertSame(1000, $colliding[1]['_script']['script']['params']['meters']);
        $this->assertSame([], $parser->errors());
    }

    public function test_a_nested_facet_evaluates_alternatives_in_their_common_parent(): void
    {
        $props = new NewProperties;
        $props->nested('orders', function (NewProperties $orders): void {
            $orders->keyword('state');
            $orders->keyword('color');
            $orders->nested('items', fn (NewProperties $items): Keyword => $items->keyword('sku'));
        });

        $raw = (new FacetParser($props))->parse('orders.color', 'orders.state:open OR orders.items.sku:book')->toRaw();
        $facet = $raw['orders.color'];
        $this->assertSame(['path' => 'orders'], $facet['nested'] ?? null);
        $filter = $facet['aggs']['orders.color']['filter']['bool']['should'];
        $this->assertSame('open', $filter[0]['bool']['must'][0]['term']['orders.state']['value']);
        $this->assertSame('orders.items', $filter[1]['bool']['must'][0]['nested']['path']);
    }

    public function test_nested_facet_filters_the_selected_items_inside_explicit_braces(): void
    {
        $props = new NewProperties;
        $props->nested('orders', fn (NewProperties $orders): Nested => $orders->nested('items', function (NewProperties $items): void {
            $items->keyword('sku');
            $items->keyword('color');
        }));
        $parser = new FacetParser($props);
        $direct = $parser->parse('orders.items.color', 'orders.items.sku:book')->toRaw();
        $explicit = $parser->parse('orders.items.color', 'orders:{items:{sku:book}}')->toRaw();

        $this->assertSame($direct['orders.items.color']['nested'], $explicit['orders.items.color']['nested']);
        $this->assertStringContainsString('"orders.items.sku"', json_encode($explicit));
        $this->assertStringNotContainsString('"path":"orders"', json_encode($explicit));
    }

    public function test_lazy_builder_preserves_explicit_score_order(): void
    {
        $connection = $this->createMock(ElasticsearchConnectionInterface::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponseContract {
            if ($request->getUri()->getPath() === '/_search') {
                $this->assertSame([['_score' => 'desc'], ['_shard_doc' => 'asc']], $request->body()['sort']);
            }

            return $request->response(new PsrResponse(200, [], '{"id":"snapshot","hits":{"hits":[]}}'));
        });

        iterator_to_array((new NewSearch($connection))->index('products')->sort('_score:desc')->lazy());
    }

    public function test_generated_embeddings_respect_the_requested_dimension(): void
    {
        $properties = new NewProperties;
        $properties->text('short')->semantic('model', dimensions: 128);
        $properties->text('long')->semantic('model', dimensions: 256);
        $api = new FakeEmbeddingsApi($this->createStub(EmbeddingsApi::class), 10);
        $body = $this->search($properties)->apis(['model' => $api])->semantic()
            ->queryString('guide', dimension: 128)->makeSearch()->toRaw();

        $this->assertSame([[['text' => 'guide', 'dims' => 128]]], $api->getBatchEmbedCalls());
        $this->assertCount(1, $body['knn']);
    }

    public function test_template_without_facets_uses_an_aggregation_object(): void
    {
        $source = (new NewTemplate($this->connection()))->id('empty')->facets('')->get()->source();

        $this->assertStringContainsString('{{^facets}}{}{{/facets}}', $source);
    }

    public function test_template_highlights_a_sparse_field_list(): void
    {
        $source = (new NewTemplate($this->connection()))->id('titles')
            ->highlighting([3 => 'title'], '<b>', '</b>')->get()->source();

        $this->assertStringContainsString('"fields":{"title":{"type":"plain","pre_tags":["<b>"]', $source);
    }

    public function test_template_preserves_a_later_parameter_marker_in_a_filter_value(): void
    {
        $properties = new NewProperties;
        $properties->keyword('category');

        $source = (new NewTemplate($this->connection()))->id('titles')->properties($properties)
            ->filters("category:'@sort(1)@endsort'")->get()->source();

        $this->assertStringContainsString('"@sort(1)@endsort"', $source);
    }

    public function test_lazy_scan_propagates_a_failed_page_response(): void
    {
        $connection = $this->createMock(ElasticsearchConnectionInterface::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(fn (ElasticsearchRequest $request): ElasticsearchResponseContract => $request->response(new PsrResponse(404, [], '{"error":{"type":"search_context_missing_exception","reason":"PIT expired"}}'))
        );

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(404);
        iterator_to_array((new RawQuery($connection, 'products', ['pit' => ['id' => 'expired']]))->lazy());
    }

    public function test_failed_pit_open_stops_before_searching(): void
    {
        $connection = $this->createMock(ElasticsearchConnectionInterface::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->expects($this->once())->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponseContract {
            $this->assertSame('/products/_pit', $request->getUri()->getPath());

            return $request->response(new PsrResponse(404, [], '{"error":{"type":"index_not_found_exception","reason":"index missing"}}'));
        });

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(404);
        iterator_to_array((new RawQuery($connection, 'products', []))->lazy());
    }

    public function test_raw_numeric_suggestion_name_is_retained(): void
    {
        $suggest = new Suggest;
        $suggest->term('0')->field('title')->text('lamp');
        $body = (new RawQuery($this->connection(), 'products', [
            'suggest' => $suggest->toRaw(),
        ]))->toMultiSearch()[1];

        $this->assertSame('{"0":{"term":{"field":"title","size":"5","sort":"score","suggest_mode":"always"},"text":"lamp"}}', json_encode($body['suggest'], JSON_THROW_ON_ERROR));
    }

    public function test_multisearch_formatter_reports_a_failed_outer_request(): void
    {
        $response = (new SigmieMultiSearchResponse)->searches([new NewQuery($this->connection())])
            ->multiSearchResponseRaw(['status' => 400, 'error' => ['type' => 'x_content_parse_exception']]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(400);
        $response->format();
    }

    public function test_formatted_hit_sources_keep_numeric_field_names(): void
    {
        $raw = ['_id' => 'result', '_source' => ['0' => 'zero', '1' => 'one']];
        $raw['inner_hits']['top']['hits']['hits'][] = $raw;
        $response = $this->search(new NewProperties)->formatResponses(['hits' => ['hits' => [$raw]]]);

        $this->assertSame('{"0":"zero","1":"one"}', json_encode($response->json('hits.0._source'), JSON_THROW_ON_ERROR));
        $this->assertSame('one', $response->json('hits.0.inner_hits.top.hits.hits.0._source.1'));
        $this->assertInstanceOf(stdClass::class, $response->json('hits.0.inner_hits.top.hits.hits.0._source'));
        $this->assertSame('zero', $response->hits()[0]->get('0'));
    }

    public function test_source_paths_try_shorter_prefixes_when_the_leaf_is_absent(): void
    {
        $hit = new Hit([
            'book.author' => ['rank' => 1, 'year' => null],
            'book' => ['author' => ['name' => 'Ada', 'year' => 2000]],
        ], '1', 1);
        $this->assertSame('Ada', $hit->get('book.author.name'));
        $this->assertNull($hit->get('book.author.year'));
        $context = (new NewContextComposer)->fields(['book.author.name', 'book.author.year'])->compose([$hit]);
        $this->assertSame('{"book.author.name":"Ada","book.author.year":null}', $context);
    }

    public function test_context_selects_numeric_fields_across_records(): void
    {
        $hit = new Hit(['rows' => [['0' => 'first'], ['0' => 'second']]], '1', 1);
        $context = (new NewContextComposer)->fields(['rows.0'])->compose([$hit]);

        $this->assertSame('{"rows.0":["first","second"]}', $context);
    }

    public function test_context_reads_partially_dotted_object_paths(): void
    {
        $hit = new Hit(['book.author' => [['name' => 'Ada'], ['name' => 'Grace']]], '1', 1);
        $context = (new NewContextComposer)->fields(['book.author.name'])->compose([$hit]);

        $this->assertSame('{"book.author.name":["Ada","Grace"]}', $context);
    }

    public function test_input_parser_preserves_a_leading_apostrophe_before_directives(): void
    {
        $parser = new InputParser;

        $this->assertSame([
            'query_string' => "'tis camera ",
            'filter_string' => "brand:'Canon'",
            'sort_string' => 'rank:desc',
        ], $parser->parse("'tis camera FILTER brand:'Canon' SORT rank:desc"));
        $this->assertSame("'camera FILTER guide' ", $parser->parse("'camera FILTER guide' SORT rank:desc")['query_string']);
    }

    public function test_sort_parser_accepts_an_explicit_document_order(): void
    {
        $parser = new SortParser;

        $this->assertSame([['_doc' => 'desc']], $parser->parse('_doc:desc'));
        $this->assertSame([['_doc' => 'asc']], $parser->parse('_doc:asc'));
        $this->assertSame(['_doc'], $parser->parse('_doc'));
    }

    public function test_inclusive_range_accepts_an_empty_quoted_keyword_bound(): void
    {
        $properties = new NewProperties;
        $properties->keyword('sku');

        $query = (new FilterParser($properties))->parse("sku:''..'ABC'")->toRaw();
        $range = $query['bool']['must'][0]['range']['sku'] ?? [];

        $this->assertSame('', $range['gte'] ?? null);
        $this->assertSame('ABC', $range['lte'] ?? null);
    }

    public function test_unquoted_padded_epoch_milliseconds_remain_a_number(): void
    {
        $properties = new NewProperties;
        $properties->datetime('created_at');

        $query = (new FilterParser($properties))->parse('created_at>=-0001')->toRaw();

        $this->assertSame(-1, $query['bool']['must'][0]['range']['created_at']['gte']);
    }

    public function test_range_operator_parser_resets_its_errors_for_each_input(): void
    {
        $parser = new RangeOperatorParser(throwOnError: false);
        $parser->parse('!=');
        $this->assertNotEmpty($parser->errors());

        $this->assertSame('gte', $parser->parse('>='));
        $this->assertSame([], $parser->errors());
    }

    public function test_facet_filter_keeps_repeated_negation_on_its_field(): void
    {
        $properties = new NewProperties;
        $properties->keyword('color');

        $parser = new FacetParser($properties);

        $this->assertSame('(NOT NOT color:red)', $parser->parseFilterString('NOT NOT color:red'));
    }

    public function test_named_vector_queries_only_contact_the_selected_provider(): void
    {
        $props = new NewProperties;
        $title = $props->text('title');
        $title->semantic('first', dimensions: 128);
        $title->semantic('unused', dimensions: 256);

        $unused = $this->createMock(EmbeddingsApi::class);
        $unused->method('maxBatchSize')->willReturn(1);
        $unused->expects($this->never())->method('batchEmbed');
        $pool = (new VectorPool($unused))->setPool(['book' => [128 => array_fill(0, 128, 1.0)]]);
        $name = $title->vectorFields()->first()->name;
        $body = $this->search($props)->apis(['unused' => $unused])->setVectorPool($pool, 'first')
            ->semantic()->queryString('book', vectorField: $name)->makeSearch()->toRaw();
        $this->assertCount(1, $body['knn']);
    }

    public function test_geo_sort_option_names_keep_the_reference_point(): void
    {
        $props = new NewProperties;
        foreach (['order', 'unit'] as $field) {
            $props->geoPoint($field);
        }

        $parser = new SortParser($props);
        foreach (['order', 'unit'] as $field) {
            [$sort] = $parser->parse($field.'[51,13]:km:desc');
            $this->assertStringContainsString('51', json_encode($sort));
            $this->assertStringContainsString('13', json_encode($sort));
        }
    }

    public function test_collapsed_scan_continues_after_custom_missing_value(): void
    {
        $connection = $this->respondingConnection(
            ['id' => 'snapshot'],
            ['hits' => ['hits' => [['_id' => 'missing', 'sort' => ['M', 0]]]]],
            ['hits' => ['hits' => [['_id' => 'last', 'fields' => ['group' => ['Z']], 'sort' => ['Z', 1]]]]],
            ['hits' => ['hits' => []]],
            [],
        );
        $query = new RawQuery($connection, 'products', [
            'collapse' => ['field' => 'group'],
            'sort' => [['group' => ['order' => 'asc', 'missing' => 'M']]],
        ]);
        $this->assertSame(['missing', 'last'], array_column(iterator_to_array($query->chunk(1)->lazy()), '_id'));
    }

    public function test_paginated_hybrid_ranks_the_same_candidates_on_each_page(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic('e', dimensions: 128);
        $search = $this->search($props)->semantic()->queryString('book', dimension: 128, vector: array_fill(0, 128, 0.1));
        $first = $search->page(1, 1)->makeSearch()->toRaw();
        $second = $search->page(2, 1)->makeSearch()->toRaw();
        $this->assertEquals($first['knn'], $second['knn']);
    }

    public function test_semantic_cursor_keeps_neighbors_beyond_the_page(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic('e', dimensions: 128);
        $body = $this->search($props)->semantic()->disableKeywordSearch()->size(1)->searchAfter([1])
            ->queryString('book', dimension: 128, vector: array_fill(0, 128, 0.1))->makeSearch()->toRaw();
        $this->assertGreaterThan(1, $body['knn'][0]['k']);
    }

    public function test_selected_facet_filters_vector_candidates(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');
        $props->text('title')->semantic('e', dimensions: 128);
        $search = $this->search($props)->semantic()->facets('brand', "brand:'dell'")->size(1)
            ->queryString('book', dimension: 128, vector: array_fill(0, 128, 0.1));
        $this->assertStringContainsString('"dell"', json_encode($search->makeSearch()->toRaw()['knn'][0]['filter']));
        $this->assertStringNotContainsString('"dell"', json_encode($search->makeFacetSearch()->toRaw()['knn'][0]['filter']));
        $this->assertStringContainsString('"dell"', json_encode($search->makeSearch()->toRaw()['knn'][0]['filter']));
    }

    public function test_semantic_template_neighbors_cover_runtime_pages(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic('e', dimensions: 128);
        $source = (new NewTemplate($this->connection()))->id('books')->properties($props)
            ->fields(['title'])->semantic()->size(1)->get()->source();
        $this->assertMatchesRegularExpression('/"k":([2-9]|[1-9]\d+)/', $source);
    }

    public function test_query_vector_pool_uses_voyage_query_embeddings(): void
    {
        $inputTypes = [];
        $client = new Client(['handler' => function (RequestInterface $request) use (&$inputTypes): FulfilledPromise {
            $inputTypes[] = json_decode((string) $request->getBody(), true)['input_type'];

            return new FulfilledPromise(new PsrResponse(200, [], '{"data":[{"index":0,"embedding":[1]}]}'));
        }]);
        $voyage = new class($client) extends VoyageEmbeddingsApi
        {
            public function __construct(Client $client)
            {
                parent::__construct('test');
                $this->client = $client;
            }
        };
        $pool = new VectorPool($voyage);
        $pool->getMany([['text' => 'book', 'dims' => 1]]);
        $pool->get('another', 1);

        $voyage->embed('document', 1);
        $this->assertSame(['query', 'query', 'document'], $inputTypes);
    }

    public function test_opensearch_collapsed_scan_seeks_by_group(): void
    {
        $connection = $this->createMock(ElasticsearchConnectionInterface::class);
        $connection->method('driver')->willReturn(new Opensearch);
        $pages = [];
        $connection->expects($this->exactly(5))->method('__invoke')->willReturnCallback(
            function (ElasticsearchRequest $request) use (&$pages): ElasticsearchResponseContract {
                if ($request->getMethod() === 'DELETE') {
                    $this->assertSame(['pit_id' => ['snapshot']], $request->body());

                    return $request->response(new PsrResponse(200, [], '{}'));
                }

                if ($request->getUri()->getPath() === '/products/_search/point_in_time') {
                    return $request->response(new PsrResponse(200, [], '{"pit_id":"snapshot"}'));
                }

                $pages[] = $request->body();
                $group = match (count($pages)) {
                    1 => 'A',
                    2 => 'B',
                    default => null,
                };
                $hits = $group === null ? [] : [
                    ['_id' => $group, 'fields' => ['group' => [$group]], 'sort' => [$group]],
                ];

                return $request->response(new PsrResponse(200, [], json_encode(['hits' => ['hits' => $hits]])));
            }
        );

        $query = new RawQuery($connection, 'products', [
            'collapse' => ['field' => 'group'],
            'sort' => [['group' => 'asc']],
        ]);

        $this->assertSame(['A', 'B'], array_column(iterator_to_array($query->chunk(1)->lazy()), '_id'));
        $this->assertArrayNotHasKey('search_after', $pages[1]);
        $this->assertSame(['gt' => 'A'], $pages[1]['post_filter']['bool']['filter'][0]['bool']['should'][0]['range']['group']);
        $this->assertSame(['gt' => 'B'], $pages[2]['post_filter']['bool']['filter'][0]['bool']['should'][0]['range']['group']);
    }

    public function test_opensearch_highlights_indexed_address_prefixes(): void
    {
        $connection = $this->createStub(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Opensearch);
        $properties = new NewProperties;
        $properties->address('address');

        $body = (new NewSearch($connection))->index('products')->properties($properties)
            ->queryString('LY')->highlighting(['address'], '<mark>', '</mark>')->makeSearch()->toRaw();

        $this->assertSame('unified', $body['highlight']['fields']['address']['type']);
        $this->assertContains('address._index_prefix', $body['highlight']['fields']['address']['matched_fields']);
    }

    public function test_blank_template_query_uses_the_empty_search_fallback(): void
    {
        $connection = $this->createMock(ElasticsearchConnectionInterface::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->expects($this->exactly(2))->method('__invoke')->willReturnCallback(
            function (ElasticsearchRequest $request): ElasticsearchResponseContract {
                $params = json_decode((string) $request->getBody(), true)['params'];
                $this->assertSame('', $params['query_string']);

                return $request->response(new PsrResponse(200, [], '{}'));
            }
        );

        $template = new ExistingScript('products', $connection);
        $template->render(['query_string' => ' ']);
        $template->run('products', ['query_string' => ' ']);
    }

    public function test_opensearch_semantic_template_guards_vectors_by_dimension(): void
    {
        $connection = $this->createMock(ElasticsearchConnectionInterface::class);
        $connection->method('driver')->willReturn(new Opensearch);
        $requests = [];
        $connection->expects($this->exactly(2))->method('__invoke')->willReturnCallback(
            function (ElasticsearchRequest $request) use (&$requests): ElasticsearchResponseContract {
                $requests[$request->getUri()->getPath()] = json_decode((string) $request->getBody(), true);

                return $request->response(new PsrResponse(200, [], '{"template_output":{}}'));
            }
        );

        $properties = new NewProperties;
        $title = $properties->text('title');
        $title->semantic(api: 'embeddings', dimensions: 128);
        $title->semantic(api: 'embeddings', dimensions: 256);

        $source = (new NewTemplate($connection))->id('products')->properties($properties)
            ->fields(['title'])->semantic()->get()->source();

        $script = new ExistingScript('products', $connection);
        $params = ['_embeddings_title' => array_fill(0, 128, 1.0)];
        $script->render($params);
        $script->run('products', $params);

        $this->assertFalse(str_contains($source, '{{#_embeddings_title.127}}'));
        $this->assertStringContainsString('{{#_sigmie_embedding_dimensions._embeddings_title.128}}', $source);
        $this->assertStringContainsString('{{#_sigmie_embedding_dimensions._embeddings_title.256}}', $source);
        foreach (['/_render/template', '/products/_search/template'] as $path) {
            $this->assertSame([128 => true], $requests[$path]['params']['_sigmie_embedding_dimensions']['_embeddings_title']);
        }
    }

    public function test_raw_formatter_hits_reject_a_timed_out_response(): void
    {
        $formatter = (new RawElasticsearchFormat)->queryResponseRaw([
            'timed_out' => true,
            'hits' => ['hits' => [['_id' => 'partial']]],
        ]);

        $this->expectException(ElasticsearchException::class);
        $formatter->hits();
    }

    public function test_inner_hit_parent_retains_repeated_relative_path_segments(): void
    {
        $props = new NewProperties;
        $props->nested('a', fn (NewProperties $outer): Object_ => $outer->object('a', fn (NewProperties $object): Nested => $object->nested('b', fn (NewProperties $middle): Nested => $middle->nested('c', fn (NewProperties $leaf): Keyword => $leaf->keyword('code')))));

        $item = $this->innerHit($props, 'a.a.b.c', [
            'field' => 'a', 'offset' => 1,
            '_nested' => ['field' => 'a.b', 'offset' => 2, '_nested' => ['field' => 'c', 'offset' => 0]],
        ], ['code' => 'X']);

        $this->assertSame(['a' => 1, 'a.a.b' => 2], $item['_parents']);
    }

    public function test_date_range_accepts_custom_and_php_date_bounds(): void
    {
        $range = new Range('day', ['>=' => new DateTimeImmutable('2024-02-01Z'), '<=' => '02/02/2024'], format: 'dd/MM/uuuu');
        $body = $range->toRaw()['range']['day'];

        $this->assertContains(Range::ISO_DATE_FORMAT, explode('||', $body['format']));
        $this->assertContains('dd/MM/uuuu', explode('||', $body['format']));
        $this->assertSame('02/02/2024', $body['lte']);
    }

    public function test_filtered_knn_clauses_remain_a_list(): void
    {
        $clause = ['field' => 'embedding', 'query_vector' => [0.5, 0.5], 'k' => 1, 'num_candidates' => 10];
        $search = new Search($this->connection());

        $this->assertSame([$clause], $search->knn([2 => $clause])->toRaw()['knn']);
        $this->assertSame($clause, $search->knn($clause)->toRaw()['knn']);
    }

    public function test_highlight_field_options_encode_as_objects(): void
    {
        $search = new Search($this->connection());
        $fields = fn (array $fields): string => json_encode($search->highlight(['fields' => $fields])->toRaw()['highlight']['fields']);

        $this->assertSame('{"title":{}}', $fields(['title' => []]));
        $this->assertSame('[{"title":{}},{"summary":{}}]', $fields([2 => ['title' => []], 4 => ['summary' => []]]));
        $this->assertSame('{"0":{"matched_fields":["title"]}}', $fields([0 => ['matched_fields' => ['title']]]));
    }

    public function test_group_hits_format_html_highlights(): void
    {
        $props = new NewProperties;
        $props->html('body');
        $props->keyword('group');

        $search = $this->search($props)->uniqueBy('group', top: 2)->highlighting(['body'], '<em>', '</em>');
        $hit = ['highlight' => ['body' => ['<b><em>Hello</b></em>']]];
        $hit['inner_hits']['top']['hits']['hits'][] = $hit;

        $response = $search->formatResponses(['hits' => ['hits' => [$hit]]]);
        $this->assertSame('<b><em>Hello</em></b>', $response->json('hits.0.inner_hits.top.hits.hits.0.highlight.body.0'));
    }

    public function test_multi_search_formats_each_search_response_slice(): void
    {
        $search = $this->createStub(MultiSearchable::class);
        $search->method('multisearchResCount')->willReturn(2);
        $search->method('formatResponses')->willReturnCallback(fn (...$responses): array => array_column($responses, 'took'));
        $query = new NewQuery($this->connection());
        $response = (new SigmieMultiSearchResponse)->searches([$search, $query])
            ->multiSearchResponseRaw(['responses' => [['took' => 1], ['took' => 2], ['took' => 3]]]);

        $this->assertSame([1, 2], $response->getSearchResult(0));
        $this->assertSame(3, $response->json('1.processing_time_ms'));
    }

    public function test_formatted_highlights_keep_numeric_field_names(): void
    {
        $raw = ['_id' => '1', 'highlight' => ['0' => ['<em>red</em>']]];
        $raw['inner_hits']['top']['hits']['hits'][] = $raw;
        $response = $this->search(new NewProperties)->formatResponses(['hits' => ['hits' => [$raw]]]);

        $this->assertSame('{"0":["<em>red<\/em>"]}', json_encode($response->json('hits.0.highlight')));
        $this->assertSame(['<em>red</em>'], $response->json('hits.0.highlight.0'));
        $this->assertInstanceOf(stdClass::class, $response->json('hits.0.inner_hits.top.hits.hits.0.highlight'));
    }

    public function test_ordered_highlight_fields_reach_elasticsearch_in_order(): void
    {
        $fields = [
            ['summary' => (object) []],
            ['title' => ['number_of_fragments' => 0]],
        ];
        $raw = (new Search($this->connection()))->highlight(['fields' => $fields])->toRaw();

        $this->assertSame($fields, $raw['highlight']['fields']);
    }

    public function test_collapsed_group_hits_use_the_requested_highlights(): void
    {
        $props = new NewProperties;
        $props->text('title');
        $props->keyword('group');

        $raw = $this->search($props)->queryString('book')->uniqueBy('group', top: 2)
            ->highlighting(['title'], '<mark>', '</mark>')->makeSearch()->toRaw();

        $this->assertSame($raw['highlight'], $raw['collapse']['inner_hits']['highlight'] ?? null);
    }

    public function test_analytics_batch_returns_requested_hit_rows(): void
    {
        $raw = ['_id' => 'row', '_source' => ['amount' => 5]];
        $response = ['responses' => [['hits' => ['hits' => [$raw]]]]];
        $connection = $this->respondingConnection($response, $response);
        $index = $this->createStub(SigmieIndex::class);
        $index->method('analytics')->willReturn(new Analytics(
            (new NewQuery($connection))->index('orders'), new NewProperties, 'created_at'
        ));
        $multi = new NewMultiSearch($connection);
        $multi->newAnalytics($index, 'created_at', 'orders')->search(fn (Search $search): Search => $search->size(1));

        $this->assertSame([$raw], $multi->hits());
        $this->assertSame(['orders' => [$raw]], $multi->groupedHits());
    }

    public function test_multi_search_group_includes_hits_from_every_query_with_that_name(): void
    {
        $multi = new NewMultiSearch($this->respondingConnection(['responses' => [
            ['hits' => ['hits' => [['_id' => 'first']]]],
            ['hits' => ['hits' => [['_id' => 'second']]]],
            ['hits' => ['hits' => [['_id' => 'third']]]],
        ]]));
        $multi->raw('books', [], 'titles');
        $multi->raw('magazines', [], 'articles');
        $multi->raw('albums', [], 'titles');

        $this->assertSame([
            'titles' => [['_id' => 'first'], ['_id' => 'third']],
            'articles' => [['_id' => 'second']],
        ], $multi->groupedHits());
    }

    public function test_embeddings_preserve_unrestricted_source_without_a_schema(): void
    {
        $body = $this->search(new NewProperties)->retrieveEmbeddingsField()->makeSearch()->toRaw();
        $this->assertArrayNotHasKey('_source', $body);
    }

    public function test_scoped_query_uses_the_unnamed_vector_pool(): void
    {
        $properties = new NewProperties;
        $properties->text('title')->semantic(api: 'title-api', dimensions: 128);
        $properties->text('summary')->semantic(api: 'summary-api', dimensions: 128);
        $vector = [1.0, ...array_fill(0, 127, 0.0)];
        $pool = (new VectorPool($this->createStub(EmbeddingsApi::class)))->setPool(['guide' => [128 => $vector]]);
        $body = $this->search($properties)->fields(['title'])->semantic()
            ->queryString('guide', fields: ['summary'])->setVectorPool($pool)->makeSearch()->toRaw();

        $this->assertSame($vector, $body['knn'][0]['query_vector'] ?? null);
    }

    public function test_multisearch_accepts_a_connection_implementing_the_contract(): void
    {
        $connection = $this->createStub(ElasticsearchConnectionInterface::class);
        $search = (new NewMultiSearch($connection))->newSearch('books');
        $this->assertSame($connection, $search->makeSearch()->getElasticsearchConnection());
    }

    public function test_rrf_applies_positional_weights_to_named_rankings(): void
    {
        $hits = (new RRF)->fuse([
            'titles' => [['_id' => 'title']],
            'authors' => [['_id' => 'author']],
        ], [1, 3]);

        $this->assertSame(['author', 'title'], array_column($hits, '_id'));
        $this->assertSame(3 * (1 / 61), $hits[0]['_score']);
    }

    public function test_semantic_template_interpolates_unicode_text(): void
    {
        $properties = new NewProperties;
        $properties->text('東京')->semantic(api: 'embed', dimensions: 128);
        $source = (new NewTemplate($this->connection()))->id('titles')->properties($properties)
            ->semantic()->get()->source();

        $this->assertStringContainsString('"query":"{{_embeddings_東京}}"', $source);
    }

    public function test_template_weights_vector_matches(): void
    {
        $properties = new NewProperties;
        $properties->text('title')->semantic(api: 'embed', dimensions: 128);
        $source = (new NewTemplate($this->connection()))->id('titles')->properties($properties)
            ->weight(['title' => 4])->semantic()->get()->source();

        $this->assertMatchesRegularExpression('/"knn":\{.*?"num_candidates":\d+,"boost":4\}/', $source);
    }

    public function test_literal_embedding_marker_survives_source_selection(): void
    {
        $properties = new NewProperties;
        $properties->text('title')->semantic(api: 'embed', dimensions: 128);
        $source = (new NewTemplate($this->connection()))->id('titles')->properties($properties)
            ->retrieve(['@_embeddings_title'])->semantic()->get()->source();

        $this->assertStringContainsString('"_source":["@_embeddings_title"]', $source);
    }

    public function test_multisearch_hit_access_works_with_a_format_only_formatter(): void
    {
        $item = ['hits' => ['hits' => [['_id' => 'a', '_source' => []]]]];
        $response = ['responses' => [$item]];
        $multi = new NewMultiSearch($this->respondingConnection($response, $response));
        $multi->newSearch('books', 'results')->formatter(new class extends AbstractFormatter
        {
            public function format(): array
            {
                return ['count' => 1];
            }
        });

        $this->assertSame('a', $multi->hits()[0]->_id);
        $this->assertSame('a', $multi->groupedHits()['results'][0]->_id);
    }

    public function test_contract_template_uses_the_fields_query_string_implementation(): void
    {
        $fields = new NewProperties;
        $fields->add(new class('sku') extends Keyword
        {
            public function queryStringQueries(array|string $queryString): array
            {
                return [new Term('canonical_sku', $queryString)];
            }
        });
        $source = (new NewTemplate($this->connection()))->id('products')->properties($fields)->get()->source();

        $this->assertStringContainsString('"canonical_sku"', $source);
    }

    public function test_contract_search_returns_the_formatter_produced_by_its_setters(): void
    {
        $formatter = new class extends RawElasticsearchFormat
        {
            public function queryResponseRaw(array $raw): static
            {
                $copy = clone $this;
                $copy->queryResponseRaw = $raw;

                return $copy;
            }
        };
        $search = $this->search(new NewProperties)->formatter($formatter);
        $this->assertSame(['took' => 3], $search->formatResponses(['took' => 3])->format());
    }

    public function test_contract_search_hits_work_with_a_format_only_formatter(): void
    {
        $formatter = new class extends AbstractFormatter
        {
            public function format(): array
            {
                return ['count' => count($this->queryResponseRaw['hits']['hits'])];
            }
        };
        $raw = ['_id' => '42', '_index' => 'products', '_source' => ['title' => 'Guide']];
        $connection = $this->respondingConnection(['responses' => [['hits' => ['hits' => [$raw]]]]]);
        $search = (new NewSearch($connection))->index('products')->formatter($formatter);

        $this->assertSame('42', $search->hits()[0]->_id);
    }

    public function test_contract_formatter_can_format_a_search_response(): void
    {
        $formatter = $this->createStub(ResponseFormater::class);
        foreach (['context', 'apis', 'errors', 'facetsResponseRaw', 'queryResponseRaw'] as $method) {
            $formatter->method($method)->willReturnSelf();
        }

        $formatter->method('format')->willReturn(['custom' => true]);
        $search = $this->search(new NewProperties)->formatter($formatter);

        $this->assertSame(['custom' => true], $search->formatResponses([])->format());
    }

    public function test_contract_search_builders_accept_named_properties(): void
    {
        foreach ([new NewSearch($this->connection()), new NewTemplate($this->connection())] as $builder) {
            $this->assertSame($builder, $builder->properties(properties: new NewProperties));
        }
    }

    public function test_contract_parsers_accept_a_named_string(): void
    {
        $this->assertSame('gte', (new RangeOperatorParser)->parse(string: '>='));
        $this->assertEquals((new FilterParser)->parse(''), (new FilterParser)->parse(string: ''));
    }

    public function test_mapped_emoji_field_is_filterable(): void
    {
        $properties = new NewProperties;
        $properties->keyword('👩‍💻');

        $raw = (new FilterParser($properties))->parse("👩‍💻:'İstanbul'")->toRaw();

        $this->assertStringContainsString('"👩‍💻"', json_encode($raw, JSON_UNESCAPED_UNICODE));
    }

    public function test_empty_keyword_range_endpoint_is_a_bound(): void
    {
        $properties = new NewProperties;
        $properties->keyword('tag');

        $parser = new FilterParser($properties);

        foreach ([['', 'z'], ['a', '']] as [$min, $max]) {
            $raw = $parser->parse(sprintf('tag:"%s".."%s"', $min, $max))->toRaw();
            $range = $raw['bool']['must'][0]['range']['tag'] ?? [];
            $this->assertSame($min, $range['gte'] ?? null);
            $this->assertSame($max, $range['lte'] ?? null);
        }
    }

    public function test_timestamp_after_a_quoted_range_bound_is_one_clause(): void
    {
        $properties = new NewProperties;
        $properties->datetime('created');

        $raw = (new FilterParser($properties))->parse('created:"2022-01-01"..2023-01-02T10:00:00Z')->toRaw();
        $this->assertSame('2023-01-02T10:00:00Z', $raw['bool']['must'][0]['range']['created']['lte']);
    }

    public function test_custom_facet_receives_the_complete_parameter(): void
    {
        $properties = new NewProperties;
        $properties->type(new class('label') extends Keyword
        {
            public function aggregation(Aggs $aggs, string $params): void
            {
                $aggs->terms($params, $this->name());
            }
        });

        $raw = (new FacetParser($properties))->parse('label:group:morning')->toRaw();
        $this->assertArrayHasKey('group:morning', $raw['label']['aggs']);
    }

    public function test_nested_facet_keeps_a_spacing_mark_in_the_child_path(): void
    {
        $props = new NewProperties;
        $props->nested('orders', function (NewProperties $orders): void {
            $orders->keyword('state');
            $orders->nested('किताब', fn (NewProperties $items): Keyword => $items->keyword('sku'));
        });

        $facet = $this->search($props)->facets('orders.state', 'orders.किताब.sku:book')
            ->makeSearch()->toRaw()['aggs']['orders.state'];
        $this->assertSame('orders', $facet['nested']['path'] ?? null);
    }

    public function test_unicode_blank_search_uses_the_empty_query_policy(): void
    {
        $properties = new NewProperties;
        $properties->keyword('題');

        $build = fn (string $text) => $this->search($properties)->fields(['題'])->queryString($text)->makeSearch()->toRaw()['query'];

        $this->assertEquals($build(''), $build("\u{3000}\u{00A0}"));
    }

    public function test_unicode_sort_separators_preserve_field_order(): void
    {
        $properties = new NewProperties;
        $properties->keyword('τιμή');

        $parser = new SortParser($properties);

        $this->assertSame(['_score', ['τιμή' => 'desc']], $parser->parse("\u{3000}_score\u{00A0}τιμή:desc\u{2009}"));
        $this->assertSame(['_score'], $parser->parse("\u{3000}"));
    }

    public function test_unicode_filter_whitespace_preserves_quoted_values(): void
    {
        $properties = new NewProperties;
        $properties->keyword('城市');

        $parser = new FilterParser($properties);
        $clause = "城市:'東\u{3000}京😀'";
        $expected = $parser->parse($clause.' OR 城市:上海')->toRaw();

        $this->assertEquals($expected, $parser->parse($clause.' OR　城市:上海')->toRaw());
        $this->assertEquals($parser->parse('')->toRaw(), $parser->parse("\u{3000}")->toRaw());
    }

    public function test_unicode_facet_whitespace_separates_clauses(): void
    {
        $properties = new NewProperties;
        $properties->keyword('עיר');
        $properties->keyword('色');

        $parser = new FacetParser($properties);

        $this->assertSame(['עיר', '色'], $parser->fields("עיר\u{2009}色"));
        $this->assertSame("(עיר:'תל\u{00A0}אביב') AND (色:青)", $parser->parseFilterString("עיר\u{00A0}: 'תל\u{00A0}אביב'\u{3000}色:青"));
    }

    public function test_combining_mark_before_an_apostrophe_keeps_the_filter_delimiter(): void
    {
        $query = "cafe\u{0301}'s ";
        $parts = (new InputParser)->parse($query."FILTER ville:'Paris'");

        $this->assertSame($query, $parts['query_string']);
        $this->assertSame("ville:'Paris'", $parts['filter_string']);
    }

    public function test_input_parser_trims_unicode_space_from_sort_and_filter(): void
    {
        $parts = (new InputParser)->parse("İzmir FILTER şehir:İzmir\u{2009}SORT şehir:asc\u{3000}");

        $this->assertSame('şehir:İzmir', $parts['filter_string']);
        $this->assertSame('şehir:asc', $parts['sort_string']);
    }

    public function test_input_parser_reads_the_sort_after_a_long_quoted_filter(): void
    {
        $filter = 'title:"'.str_repeat('long FILTER phrase ', 2000).'"';
        $parts = (new InputParser)->parse('camera FILTER '.$filter.' SORT rank:desc');

        $this->assertSame('camera ', $parts['query_string']);
        $this->assertTrue($filter === $parts['filter_string'], 'The filter contains only the quoted title.');
        $this->assertSame('rank:desc', $parts['sort_string']);
    }

    public function test_unicode_template_embedding_parameter_expands(): void
    {
        $properties = new NewProperties;
        $properties->text('東京')->semantic(api: 'embeddings', dimensions: 128);
        $source = (new NewTemplate($this->connection()))->id('products')->properties($properties)->semantic()->get()->source();

        $this->assertStringContainsString('{{#toJson}}_embeddings_東京{{/toJson}}', $source);
    }

    public function test_non_latin_text_produces_distinct_fake_embeddings(): void
    {
        $api = new FakeEmbeddingsApi($this->createMock(EmbeddingsApi::class));

        $this->assertNotSame($api->embed('東京', 128), $api->embed('Αθήνα', 128));
    }

    public function test_fake_rerank_matches_non_latin_query_tokens(): void
    {
        $api = new FakeRerankApi($this->createMock(RerankApi::class));

        $this->assertSame([1], array_column($api->rerank(['東京', 'Αθήνα'], 'ΑΘΉΝΑ', 1), 'index'));
    }

    public function test_disabled_autocomplete_omits_typed_prefix(): void
    {
        $props = new NewProperties;
        $props->completion('title');

        $body = $this->search($props)->autocomplete(false)->autocompletePrefix('star')->makeSearch()->toRaw();

        $this->assertArrayNotHasKey('suggest', $body);
    }

    public function test_filter_after_sort_stays_a_filter(): void
    {
        $parts = (new InputParser)->parse("camera SORT rank:desc FILTER brand:'Night Vision'");

        $this->assertSame('camera', trim($parts['query_string']));
        $this->assertSame('rank:desc', $parts['sort_string']);
        $this->assertSame("brand:'Night Vision'", $parts['filter_string']);
    }

    public function test_typo_tolerance_uses_exact_field_names(): void
    {
        $props = new NewProperties;
        $props->text('01');
        $props->text('1');

        $search = $this->search($props)->fields(['01'])->typoTolerantAttributes(['1'])
            ->queryString('sleping')->makeSearch();
        $template = (new NewTemplate($this->connection()))->id('products')->properties($props)
            ->fields(['01'])->typoTolerantAttributes(['1'])->get();

        $this->assertStringNotContainsString('fuzziness', json_encode($search->toRaw()['query']));
        $this->assertStringNotContainsString('fuzziness', $template->source());
    }

    public function test_search_as_you_type_uses_configured_typo_tolerance(): void
    {
        $props = new NewProperties;
        $props->searchAsYouType('title');

        $query = $this->search($props)->typoTolerantAttributes(['title'])->typoTolerance(4, 8)
            ->queryString('sleping be')->makeSearch()->toRaw()['query'];

        $this->assertStringContainsString('"fuzziness":"AUTO:4,8"', json_encode($query));
    }

    public function test_a_semantic_template_respects_explicit_zero_min_score(): void
    {
        $properties = new NewProperties;
        $properties->text('title')->semantic(api: 'test-embeddings');
        $template = (new NewTemplate($this->connection()))->id('catalog')->properties($properties)->fields(['title'])->semantic();

        $this->assertStringContainsString('{{^minscore}}0.01{{/minscore}}', $template->minScore(0.01)->get()->source());
        $this->assertStringContainsString('{{^minscore}}0{{/minscore}}', $template->minScore(0)->get()->source());
    }

    public function test_an_empty_retrieve_list_disables_source_retrieval(): void
    {
        $properties = new NewProperties;
        $properties->text('title');

        $search = $this->search($properties)->except(['private']);

        $this->assertSame(['includes' => ['title'], 'excludes' => ['private']], $search->makeSearch()->toRaw()['_source']);
        $this->assertSame(false, $search->retrieve([])->makeSearch()->toRaw()['_source'] ?? null);

        $template = (new NewTemplate($this->connection()))->id('catalog')->properties($properties)->retrieve([]);
        $this->assertStringContainsString('"_source":false', $template->get()->source());
    }

    public function test_typo_tolerance_matches_numeric_field_names_exactly(): void
    {
        $properties = new NewProperties;
        $properties->text('0');
        $properties->text('00');

        $search = $this->search($properties)->typoTolerantAttributes(['0'])->queryString('lamp');

        $body = json_encode($search->makeSearch()->toRaw(), JSON_THROW_ON_ERROR);
        $this->assertMatchesRegularExpression('/"0":\{[^}]*"fuzziness":"AUTO:3,6"/', $body);
        $this->assertDoesNotMatchRegularExpression('/"00":\{[^}]*"fuzziness"/', $body);

        $template = (new NewTemplate($this->connection()))->id('catalog')->properties($properties)->typoTolerantAttributes(['0']);
        $this->assertDoesNotMatchRegularExpression('/"00":\{[^}]*"fuzziness"/', $template->get()->source());
    }

    public function test_a_custom_match_keeps_zero_fuzziness_without_a_builder_override(): void
    {
        $properties = new NewProperties;
        $properties->text('title')->withQueries(fn (string $query): array => [new Match_('title', $query, fuzziness: '0')]);

        $body = $this->search($properties)->queryString('lamp')->makeSearch()->toRaw();
        $this->assertStringContainsString('"fuzziness":"0"', json_encode($body, JSON_THROW_ON_ERROR));

        $template = (new NewTemplate($this->connection()))->id('catalog')->properties($properties);
        $this->assertStringContainsString('"fuzziness":"0"', $template->get()->source());
    }

    public function test_rerank_preserves_objects_selected_inside_a_list(): void
    {
        $hit = new Document(['chapters' => [
            ['author' => ['name' => 'Ada']],
            ['author' => null],
        ]]);

        $payload = NewRerank::documentPayloads([$hit], ['chapters.author']);

        $this->assertSame(['chapters.author' => [['name' => 'Ada'], null]], Yaml::parse($payload[0]));
    }

    public function test_a_rerank_payload_keeps_explicit_null_fields(): void
    {
        $payload = NewRerank::documentPayloads([
            new Document(['price' => null, 'details' => ['price' => null], 'active' => false, 'count' => 0], 'a'),
        ], ['price', 'details.price', 'missing', 'active', 'count']);

        $this->assertSame(['price' => null, 'details.price' => null, 'active' => false, 'count' => 0], Yaml::parse($payload[0]));
    }

    public function test_a_rerank_payload_keeps_a_field_named_zero(): void
    {
        $payload = NewRerank::documentPayloads([new Document(['0' => 'zero'])], ['0']);

        $this->assertSame("'0': zero", trim($payload[0]));
    }

    public function test_a_rerank_hit_preserves_null_index_and_index_zero(): void
    {
        $api = $this->createStub(RerankApi::class);
        $api->method('rerank')->willReturn([['index' => 0, 'score' => 0.0], ['index' => 1, 'score' => 0.0]]);
        $hits = (new NewRerank($api))->query('lamp')->rerank([
            ['_id' => 'a', '_index' => null],
            ['_id' => 'b', '_index' => '0'],
        ]);

        $this->assertFalse(isset($hits[0]->_index));
        $this->assertSame('0', $hits[1]->_index);
    }

    public function test_price_facets_distinguish_null_bounds_from_zero(): void
    {
        $properties = new NewProperties;
        $properties->price();

        $response = new SigmieSearchResponse($properties->get());

        foreach ([null, 0.0] as $value) {
            $response->facetsResponseRaw(['aggregations' => ['price' => [
                'price_histogram' => ['buckets' => []],
                'price_min' => ['value' => $value],
                'price_max' => ['value' => $value],
            ]]]);

            $this->assertSame($value, $response->facet('price')['min']);
            $this->assertSame($value, $response->facet('price')['max']);
        }
    }

    public function test_lazy_starts_fresh_after_visiting_a_page(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponseContract {
            $body = json_decode((string) $request->getBody(), true);
            $response = match ($request->getUri()->getPath()) {
                '/products/_pit' => ['id' => 'snapshot'],
                '/_search' => ['hits' => ['hits' => isset($body['search_after']) ? [] : [['_id' => 'first']]]],
                '/_pit' => ['succeeded' => true],
            };

            return $request->response(new PsrResponse(200, [], json_encode($response)));
        });
        $search = (new NewSearch($connection))->index('products')->searchAfter(['previous-page']);

        $this->assertSame(['first'], array_column(iterator_to_array($search->lazy()), '_id'));
        $this->assertSame(['previous-page'], $search->makeSearch()->toRaw()['search_after']);
    }

    public function test_a_short_pit_page_finishes_the_scan(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $searches = 0;
        $closed = [];
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use (&$searches, &$closed): ElasticsearchResponseContract {
            $body = json_decode((string) $request->getBody(), true);
            $response = match ($request->getUri()->getPath()) {
                '/products/_pit' => ['id' => 'opened'],
                '/_search' => match (++$searches) {
                    1 => ['pit_id' => 'full', 'hits' => ['total' => ['value' => 2], 'hits' => [
                        ['_id' => 'one', 'sort' => [1]], ['_id' => 'two', 'sort' => [2]],
                    ]]],
                    2 => ['pit_id' => 'short', 'hits' => ['total' => ['value' => 10], 'hits' => [
                        ['_id' => 'three', 'sort' => [3]],
                    ]]],
                    default => ['hits' => ['hits' => []]],
                },
                '/_pit' => ['succeeded' => (bool) ($closed[] = $body['id'])],
            };

            return $request->response(new PsrResponse(200, [], json_encode($response)));
        });
        $query = (new RawQuery($connection, 'products', []))->chunk(2);

        $this->assertSame(['one', 'two', 'three'], array_column(iterator_to_array($query->lazy()), '_id'));
        $this->assertSame(['short'], $closed);
        $this->assertSame(2, $searches);
    }

    public function test_breaking_on_a_buffered_final_page_has_already_closed_the_pit(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $closed = [];
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use (&$closed): ElasticsearchResponseContract {
            $body = json_decode((string) $request->getBody(), true);
            $response = match ($request->getUri()->getPath()) {
                '/products/_pit' => ['id' => 'opened'],
                '/_search' => ['pit_id' => 'last', 'hits' => ['hits' => [
                    ['_id' => 'one', 'sort' => [1]], ['_id' => 'two', 'sort' => [2]],
                ]]],
                '/_pit' => ['succeeded' => (bool) ($closed[] = $body['id'])],
            };

            return $request->response(new PsrResponse(200, [], json_encode($response)));
        });
        $iterator = (new RawQuery($connection, 'products', []))->chunk(3)->lazy();

        foreach ($iterator as $hit) {
            $this->assertSame('one', $hit->_id);
            break;
        }

        $this->assertSame(['last'], $closed);
        $iterator->next();
        $this->assertSame('two', $iterator->current()->_id);
        $iterator->next();
        $this->assertFalse($iterator->valid());
        $this->assertSame(['last'], $closed);
    }

    public function test_failed_pit_close_preserves_the_search_error(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $closed = [];
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use (&$closed): ElasticsearchResponseContract {
            if ($request->getMethod() === 'DELETE') {
                $closed[] = json_decode((string) $request->getBody(), true)['id'];

                throw new ElasticsearchException(['type' => 'search_context_missing_exception'], 404);
            }

            $response = $request->getUri()->getPath() === '/products/_pit'
                ? ['id' => 'opened']
                : ['pit_id' => 'latest', 'timed_out' => true];

            return $request->response(new PsrResponse(200, [], json_encode($response)));
        });

        try {
            iterator_to_array((new RawQuery($connection, 'products', []))->lazy());
            $this->fail('Expected the PIT search to fail.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertTrue($elasticsearchException->json('timed_out'));
            $this->assertSame(['latest'], $closed);
        }
    }

    public function test_lazy_raw_reads_every_hit_despite_a_page_termination_limit(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponseContract {
            $body = json_decode((string) $request->getBody(), true);
            $response = match ($request->getUri()->getPath()) {
                '/products/_pit' => ['id' => 'snapshot'],
                '/_pit' => ['succeeded' => true],
                '/_search' => ['hits' => ['hits' => isset($body['terminate_after'])
                    ? [['_id' => 'one']]
                    : [['_id' => 'one'], ['_id' => 'two']]]],
            };

            return $request->response(new PsrResponse(200, [], json_encode($response)));
        });
        $raw = new RawQuery($connection, 'products', ['terminate_after' => 1]);

        $this->assertSame(['one', 'two'], array_column(iterator_to_array($raw->lazy()), '_id'));
        $this->assertSame(1, $raw->toMultiSearch()[1]['terminate_after']);
    }

    /** @dataProvider explicitlySortedScans */
    public function test_lazy_keeps_explicit_document_or_score_order(string $field, bool $raw): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use ($field): ElasticsearchResponseContract {
            $body = json_decode((string) $request->getBody(), true);
            $response = match ($request->getUri()->getPath()) {
                '/products/_pit' => ['id' => 'snapshot'],
                '/_pit' => ['succeeded' => true],
                '/_search' => ['hits' => ['hits' => match ($body['search_after'] ?? null) {
                    null => [['_id' => ($body['sort'][0][$field] ?? null) === 'desc' ? 'last' : 'first', 'sort' => [1]]],
                    [1] => [['_id' => ($body['sort'][0][$field] ?? null) === 'desc' ? 'first' : 'last', 'sort' => [2]]],
                    [2] => [],
                }]],
            };

            return $request->response(new PsrResponse(200, [], json_encode($response)));
        });
        $query = $raw
            ? new RawQuery($connection, 'products', ['sort' => [[$field => 'desc']]])
            : (new NewQuery($connection, 'products'))->sort([[$field => 'desc']]);

        $this->assertSame(['last', 'first'], array_column(iterator_to_array($query->chunk(1)->lazy()), '_id'));
    }

    public static function explicitlySortedScans(): array
    {
        return ['raw document sort' => ['_doc', true], 'query score sort' => ['_score', false]];
    }

    public function test_multi_search_preserves_a_match_all_raw_query(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponseContract {
            $lines = explode("\n", (string) $request->getBody());
            $this->assertSame('{}', $lines[1]);

            return $request->response(new PsrResponse(200, [], '{"responses":[{"hits":{"hits":[{"_id":"one"}]}},{"hits":{"hits":[{"_id":"two"}]}}]}'));
        });
        $multi = new NewMultiSearch($connection);
        $multi->raw('products', []);
        $multi->raw('orders', ['size' => 1]);

        $this->assertSame(['one', 'two'], array_column($multi->hits(), '_id'));
    }

    public function test_reused_lazy_search_keeps_minimum_score_inside_collapse(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);
        $props->keyword('group');
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $minimumScores = [];
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use (&$minimumScores): ElasticsearchResponseContract {
            $body = json_decode((string) $request->getBody(), true);
            if (isset($body['pit'])) {
                $minimumScores[] = $body['query']['function_score']['min_score'] ?? null;
            }

            return $request->response(new PsrResponse(200, [], '{"id":"snapshot","hits":{"hits":[]}}'));
        });
        $search = (new NewSearch($connection))->index('products')->properties($props)
            ->semantic()->queryString('book', dimension: 128, vector: array_fill(0, 128, 0.1))
            ->uniqueBy('group', top: 2)->minScore(0.5);

        iterator_to_array($search->lazy());
        iterator_to_array($search->semantic(false)->lazy());

        $this->assertSame([0.5, 0.5], $minimumScores);
    }

    public function test_nested_lazy_search_keeps_the_outer_inner_hit_limit(): void
    {
        $props = new NewProperties;
        $props->nested('reviews', fn (NewProperties $props): Text => $props->text('comment'));

        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $nested = ['hits' => ['hits' => [
            ['_nested' => ['field' => 'reviews', 'offset' => 0], '_source' => ['comment' => 'first']],
            ['_nested' => ['field' => 'reviews', 'offset' => 1], '_source' => ['comment' => 'second']],
        ], 'total' => ['value' => 2]]];
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use ($nested): ElasticsearchResponseContract {
            $body = json_decode((string) $request->getBody(), true);
            $response = match ($request->getUri()->getPath()) {
                '/products/_pit' => ['id' => 'snapshot'],
                '/_pit' => ['succeeded' => true],
                '/_search' => ['hits' => ['hits' => isset($body['search_after']) ? [] : [
                    ['_id' => 'a', 'sort' => [1], 'inner_hits' => ['reviews#1' => $nested]],
                    ['_id' => 'b', 'sort' => [2], 'inner_hits' => ['reviews#1' => $nested]],
                ]]],
            };

            return $request->response(new PsrResponse(200, [], json_encode($response)));
        });
        $search = (new NewSearch($connection))->index('products')->properties($props)
            ->queryString('product')->innerHits('reviews.comment', 1);
        $outer = $search->lazy();

        $this->assertCount(1, $outer->current()->matches('reviews'));
        $inner = iterator_to_array($search->innerHits('reviews.comment', 2)->lazy());
        $this->assertCount(2, $inner[0]->matches('reviews'));
        $outer->next();

        $this->assertCount(1, $outer->current()->matches('reviews'));
    }

    public function test_pipeline_simulation_serializes_document_sources_as_objects(): void
    {
        $connection = $this->createMock(ElasticsearchConnectionInterface::class);
        $connection->expects($this->once())->method('__invoke')->willReturnCallback(
            function (ElasticsearchRequest $request): ElasticsearchResponseContract {
                $body = json_decode((string) $request->getBody());

                $this->assertSame('{}', json_encode($body->docs[0]->_source));
                $this->assertSame('{"0":"Star Trek"}', json_encode($body->docs[1]->_source));

                return HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, [], '{"docs":[]}'));
            }
        );

        (new Pipeline($connection, 'autocomplete'))->simulate([
            ['_source' => []],
            ['_source' => ['0' => 'Star Trek']],
        ]);
    }

    public function test_set_processor_keeps_weighted_completion_values(): void
    {
        $value = ['input' => ['Star Trek', 'ST'], 'weight' => 8, 'contexts' => ['genre' => ['sci-fi']]];
        $processor = (new Set)->field('suggest')->value($value);

        $this->assertSame(['set' => ['field' => 'suggest', 'value' => $value]], $processor->toRaw());
    }

    public function test_a_title_converted_to_completion_only_builds_a_suggestion(): void
    {
        $props = new NewProperties;
        $title = $props->title('title')->completion();
        $body = $this->search($props)
            ->fields(['title'])
            ->queryString('star')
            ->autocompletePrefix('star')
            ->makeSearch()
            ->toRaw();

        $this->assertSame([], $title->queries('star'));
        $this->assertSame('title', $body['suggest']['autocompletion']['completion']['field']);
        $this->assertStringNotContainsString('"match_phrase_prefix"', json_encode($body['query']));

        $title->withQueries(fn (string $query): array => [new Term('category', $query)]);
        $this->assertSame(
            ['category' => ['value' => 'star', 'boost' => 1.0]],
            $title->queryStringQueries('star')[0]->toRaw()['term'],
        );
    }

    public function test_named_pool_seed_stays_with_its_model(): void
    {
        $api = $this->createStub(EmbeddingsApi::class);
        $search = $this->search(new NewProperties)
            ->setVectorPool(new VectorPool($api), 'first')
            ->setVectorPool(new VectorPool($api), 'second')
            ->setVectorPool(['book' => [2 => [1.0, 0.0]]], 'first');

        $this->assertTrue($search->getVectorPool('first')->has('book', 2));
        $this->assertFalse($search->getVectorPool('second')->has('book', 2));
    }

    public function test_replacing_an_embedding_api_discards_its_cached_vectors(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic('model', dimensions: 128);
        $search = $this->search($props)->semantic()->queryString('book');

        foreach ([[1.0, 0.0], [0.0, 1.0]] as $components) {
            $vector = array_pad($components, 128, 0.0);
            $api = $this->createStub(EmbeddingsApi::class);
            $api->method('maxBatchSize')->willReturn(10);
            $api->method('batchEmbed')->willReturn([['text' => 'book', 'dims' => 128, 'vector' => $vector]]);
            $body = $search->apis(['model' => $api])->makeSearch()->toRaw();

            $this->assertSame($vector, $body['knn'][0]['query_vector']);
        }
    }

    public function test_semantic_field_weight_scales_its_own_clause(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic('e', dimensions: 128);
        $props->text('body')->semantic('e', dimensions: 128);
        $body = $this->search($props)->semantic()->weight(['title' => 4])
            ->queryString('book', vector: array_fill(0, 128, 0.1))->makeSearch()->toRaw();

        $this->assertEquals([4, 1], array_column($body['knn'], 'boost'));
    }

    public function test_elasticsearch_semantic_search_uses_a_knn_safe_highlighter(): void
    {
        $props = new NewProperties;
        $props->boost('priority');
        $props->text('title')->semantic('e', dimensions: 128);

        $body = $this->search($props)->semantic()
            ->queryString('book', vector: array_fill(0, 128, 0.1))
            ->highlighting(['title'], '<em>', '</em>')
            ->makeSearch()->toRaw();

        $this->assertSame('unified', $body['highlight']['fields']['title']['type']);
    }

    public function test_semantic_field_scope_uses_exact_names(): void
    {
        $props = new NewProperties;
        $props->text('1')->semantic('e', dimensions: 128);
        $props->text('01')->semantic('e', dimensions: 128);
        $body = $this->search($props)->semantic()->queryString('book', fields: ['01'],
            vector: array_fill(0, 128, 0.1))->makeSearch()->toRaw();

        $this->assertCount(1, $body['knn']);
        $this->assertStringStartsWith('_embeddings.01.', $body['knn'][0]['field']);
    }

    public function test_provider_query_normalization_follows_each_field(): void
    {
        $props = new NewProperties;
        $props->text('raw')->semantic('e', dimensions: 128)->euclideanSimilarity()->normalizeVector(false);
        $props->text('unit')->semantic('e', dimensions: 128);
        $api = $this->createStub(EmbeddingsApi::class);
        $api->method('maxBatchSize')->willReturn(10);
        $vector = array_pad([3.0, 4.0], 128, 0.0);
        $api->method('batchEmbed')->willReturn([['text' => 'book', 'dims' => 128, 'vector' => $vector]]);

        $body = $this->search($props)->apis(['e' => $api])->semantic()->queryString('book')->makeSearch()->toRaw();
        $this->assertSame($vector, $body['knn'][0]['query_vector']);
        $this->assertSame(0.6, $body['knn'][1]['query_vector'][0]);
    }

    public function test_supplied_vector_does_not_search_other_dimensions_in_a_warm_pool(): void
    {
        $props = new NewProperties;
        $props->text('short')->semantic('e', dimensions: 128);
        $props->text('long')->semantic('e', dimensions: 256);
        $api = $this->createMock(EmbeddingsApi::class);
        $api->expects($this->never())->method('embed');
        $pool = (new VectorPool($api))->setPool(['book' => [256 => array_fill(0, 256, 0.0625)]]);
        $body = $this->search($props)->setVectorPool($pool, 'e')->semantic()
            ->queryString('book', dimension: 128, vector: array_fill(0, 128, 0.1))->makeSearch()->toRaw();

        $this->assertCount(1, $body['knn']);
    }

    public function test_not_is_a_field_name_in_the_facet_list(): void
    {
        $props = new NewProperties;
        $props->keyword('not');
        $props->keyword('brand');

        $search = $this->search($props)->facets('not brand', throwOnError: true);
        $this->assertSame(['not', 'brand'], array_keys($search->makeSearch()->toRaw()['aggs']));
        $this->assertSame(['not', 'brand'], array_keys($search->formatResponses([])->formatFacets()));
    }

    public function test_nested_facet_applies_a_descendant_selection_inside_the_order(): void
    {
        $props = new NewProperties;
        $props->nested('orders', function (NewProperties $orders): void {
            $orders->keyword('state');
            $orders->nested('items', fn (NewProperties $items): Keyword => $items->keyword('sku'));
        });

        $search = $this->search($props)->facets('orders.state', 'orders.items.sku:book');
        $facet = $search->makeSearch()->toRaw()['aggs']['orders.state'];
        $this->assertSame('orders', $facet['nested']['path'] ?? null);
        $filter = $facet['aggs']['orders.state']['filter'];
        $this->assertStringContainsString('"path":"orders.items"', json_encode($filter));
        $this->assertStringContainsString('"book"', json_encode($filter));
    }

    public function test_a_custom_metric_and_facet_can_share_a_name(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');
        $props->number('rating');

        $search = $this->search($props)->facets('brand')->aggregate(
            fn (Aggs $aggs): Max => $aggs->max('brand', 'rating')
        );
        $wire = $search->makeSearch()->toRaw()['aggs'];
        $this->assertCount(2, $wire);
        $raw = [];
        foreach ($wire as $name => $aggregation) {
            $raw[$name] = isset($aggregation['max'])
                ? ['value' => 9]
                : ['doc_count' => 2, 'brand' => ['buckets' => [['key' => 'dell', 'doc_count' => 2]]]];
        }

        $response = $search->formatResponses(['aggregations' => $raw]);
        $this->assertSame(['value' => 9], $response->aggregation('brand'));
        $this->assertSame(['dell' => 2], $response->facet('brand'));
    }

    public function test_highlighting_an_explicit_multifield_uses_its_tags(): void
    {
        $props = new NewProperties;
        $props->name('title');

        $body = $this->search($props)->highlighting(['title.title_text'], '<b>', '</b>')->makeSearch()->toRaw();
        $field = $body['highlight']['fields']['title.title_text'];
        $this->assertSame(['<b>'], $field['pre_tags']);
        $this->assertSame(['</b>'], $field['post_tags']);
    }

    public function test_dotted_highlight_field_returns_its_fragments(): void
    {
        $response = $this->search(new NewProperties)->formatResponses(['hits' => ['hits' => [[
            '_id' => '1',
            'highlight' => ['title.title_text' => ['<b>shoe</b>']],
        ]]]]);

        $this->assertSame(['<b>shoe</b>'], $response->json('hits.0.highlight.title.title_text'));
    }

    public function test_collapsed_hits_use_the_requested_source_fields(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');
        $props->keyword('sku');

        $body = $this->search($props)->retrieve(['sku'])->uniqueBy('brand', top: 2)->makeSearch()->toRaw();

        $this->assertSame(['sku'], $body['collapse']['inner_hits']['_source']['includes'] ?? null);
    }

    public function test_generated_inner_hits_do_not_consume_a_custom_name_on_the_same_path(): void
    {
        $properties = new NewProperties;
        $properties->nested('reviews', fn (NewProperties $reviews): Text => $reviews->text('comment'));

        $search = $this->search($properties)->queryString('battery')->innerHits('reviews');
        $search->makeSearch();

        $custom = ['hits' => ['total' => 9, 'hits' => []]];
        $generated = ['hits' => ['total' => 1, 'hits' => []]];
        $hit = ['inner_hits' => ['reviews#999' => $custom, 'reviews#1' => $generated]];

        $result = $search->formatResponses(['hits' => ['hits' => [$hit]]])->json('hits.0');

        $this->assertSame(['reviews#999' => $custom], $result['inner_hits'] ?? []);
        $this->assertSame(1, $result['_matches']['reviews']['total']);
    }

    public function test_custom_multi_search_result_retains_its_scalar_value(): void
    {
        $search = $this->createStub(MultiSearchable::class);
        $search->method('multisearchResCount')->willReturn(1);
        $search->method('formatResponses')->willReturn(42);
        $response = (new SigmieMultiSearchResponse)->searches([$search])
            ->multiSearchResponseRaw(['responses' => [['took' => 1]]]);

        $this->assertSame(42, $response->getSearchResult(0));
    }

    public function test_multi_search_formatter_propagates_a_request_level_error(): void
    {
        $response = (new SigmieMultiSearchResponse)->searches([new NewQuery($this->connection())])
            ->multiSearchResponseRaw(['error' => ['type' => 'index_not_found_exception'], 'status' => 404]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(404);
        $response->format();
    }

    public function test_raw_retriever_omits_conflicting_search_defaults(): void
    {
        $retriever = ['standard' => ['query' => ['match_all' => (object) []]]];
        $raw = (new Search($this->connection()))->addRaw('retriever', $retriever)->toRaw();

        $this->assertSame($retriever, $raw['retriever']);
        $this->assertArrayNotHasKey('query', $raw);
        $this->assertArrayNotHasKey('sort', $raw);
    }

    public function test_explicit_ordered_highlight_objects_normalize_empty_options(): void
    {
        $search = new Search($this->connection());
        $fields = [(object) ['options' => []], (object) ['pre_tags' => []]];
        $body = $search->highlight(['fields' => $fields])->toRaw();

        $this->assertSame('[{"options":{}},{"pre_tags":{}}]', json_encode($body['highlight']['fields']));
    }

    public function test_custom_inner_hits_with_hashes_are_preserved(): void
    {
        $props = new NewProperties;
        $props->nested('reviews', fn (NewProperties $reviews): Text => $reviews->text('comment'));

        $search = $this->search($props)->queryString('battery')->innerHits('reviews');
        $search->makeSearch();

        $custom = ['hits' => ['total' => ['value' => 0], 'hits' => []]];
        $response = $search->formatResponses(['hits' => ['hits' => [[
            '_id' => '1',
            'inner_hits' => ['custom#recent' => $custom, 'archive#1' => $custom, 'reviews#1' => $custom],
        ]]]]);

        $this->assertSame(['custom#recent' => $custom, 'archive#1' => $custom], $response->json('hits.0.inner_hits'));
    }

    public function test_vector_retrieval_adds_embeddings_to_an_explicit_projection(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'embeddings', dimensions: 128);
        $body = $this->search($props)->retrieve(['title'])->retrieveEmbeddingsField()->makeSearch()->toRaw();

        $this->assertSame(['title', '_embeddings'], $body['_source']);
    }

    public function test_infinite_scores_survive_hydration_and_json_encoding(): void
    {
        $http = new HttpSearch(200, [], '{"hits":{"hits":[{"_id":"1","_score":"Infinity"}]}}');
        $hit = $http->hits()[0];
        $this->assertSame(INF, $hit->_score);
        $this->assertSame('Infinity', json_decode(json_encode($hit, JSON_THROW_ON_ERROR), true)['_score']);
    }

    public function test_serialized_document_sources_retain_numeric_field_names(): void
    {
        $http = new HttpSearch(200, [], '{"hits":{"hits":[{"_id":"1","_score":1,"_source":{"0":"zero"}}]}}');
        $hit = $http->hits()[0];
        $source = json_decode(json_encode($hit, JSON_THROW_ON_ERROR))->_source;
        $this->assertInstanceOf(stdClass::class, $source);
        $this->assertSame('zero', $source->{'0'});
        $this->assertSame($hit->_source, Hit::fromRaw($hit->jsonSerialize())->_source);
    }

    public function test_opensearch_advances_a_collapsed_cursor_without_native_search_after(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Opensearch);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponseContract {
            $lines = explode("\n", trim((string) $request->getBody()));
            $body = json_decode($lines[1], true, flags: JSON_THROW_ON_ERROR);

            if (isset($body['collapse'], $body['search_after'])) {
                $result = ['status' => 400, 'error' => ['reason' => 'cannot use collapse in conjunction with search_after']];
            } else {
                $this->assertSame(['field' => 'group'], $body['collapse']);
                $this->assertSame(['group' => ['lt' => 'C']], $body['post_filter']['bool']['filter'][1]['bool']['should'][0]['range']);
                $this->assertStringContainsString('allowed', json_encode($body['post_filter']['bool']['filter'][0]));
                $result = ['hits' => ['hits' => [
                    ['_id' => 'next', '_source' => ['group' => 'B'], 'sort' => ['B']],
                ]]];
            }

            return $request->response(new PsrResponse(200, [], json_encode(['responses' => [$result]])));
        });

        $properties = new NewProperties;
        $properties->keyword('group');
        $properties->keyword('status')->facetDisjunctive();
        $hits = (new NewSearch($connection))->index('products')->properties($properties)
            ->uniqueBy('group')->sort('group:desc')->facets('status', 'status:allowed')
            ->searchAfter(['C'])->get()->hits();

        $this->assertSame(['next'], array_column($hits, '_id'));
    }

    public function test_cursor_page_has_no_known_page_number(): void
    {
        $result = $this->search(new NewProperties)->size(1)->searchAfter(['a'])
            ->formatResponses(['hits' => ['total' => 2, 'hits' => [['_id' => '2', 'sort' => ['b']]]]]);

        $this->assertNull($result->format()['page']);
        $this->assertSame(2, $result->format()['total_pages']);
    }

    public function test_multi_search_does_not_turn_a_failed_query_into_empty_hits(): void
    {
        $raw = ['responses' => [['status' => 404, 'error' => ['type' => 'index_not_found_exception']]]];
        $result = (new SigmieMultiSearchResponse)->searches([new NewQuery($this->connection())])
            ->multiSearchResponseRaw($raw);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(404);
        $result->format();
    }

    public function test_hits_without_stored_ids_are_readable(): void
    {
        $raw = ['hits' => ['hits' => [['_index' => 'books', '_score' => 1]]]];
        $search = $this->search(new NewProperties)->formatResponses($raw);
        $http = new HttpSearch(200, [], json_encode($raw));

        foreach ([$search, $http, (new RawElasticsearchFormat)->queryResponseRaw($raw)] as $result) {
            $hit = $result->hits()[0];
            $this->assertNull($hit->toArray()['_id']);
            $this->assertSame('books', (new RerankedHit($hit, 0.8))->_index);
        }
    }

    public function test_serializing_hits_preserves_their_search_metadata(): void
    {
        $raw = ['_index' => 'books-2', '_routing' => 'tenant', '_ignored' => ['bad'],
            '_id' => '1', '_score' => null, 'sort' => ['g'], '_source' => ['title' => 'Red'],
            'fields' => ['genre' => ['novel']], 'inner_hits' => ['group' => ['hits' => ['hits' => []]]]];
        $response = $this->search(new NewProperties)->formatResponses(['hits' => ['hits' => [$raw]]]);
        $hit = $response->hits()[0];
        $this->assertEquals($raw, $hit->toArray());
        $this->assertEquals([...$raw, '_rerank_score' => 0.5], (new RerankedHit($hit, 0.5))->jsonSerialize());
    }

    public function test_untracked_hit_totals_stay_unknown(): void
    {
        $raw = ['hits' => ['hits' => [['_id' => '1', '_score' => null]]]];
        $result = $this->search(new NewProperties)->formatResponses($raw);
        $this->assertNull($result->total());
        $this->assertNull($result->format()['total_pages']);
        $http = new HttpSearch(200, [], json_encode($raw));
        $this->assertNull($http->total());
        $multi = (new SigmieMultiSearchResponse)->searches([new NewQuery($this->connection())])
            ->multiSearchResponseRaw(['responses' => [$raw]]);
        $this->assertNull($multi->format()[0]['total']);
        $query = new NewQuery($this->connection());
        $this->assertNull($query->sliceMultiSearchResponse([$raw])['total']);
    }

    public function test_capped_counts_do_not_claim_an_exact_last_page(): void
    {
        $result = $this->search(new NewProperties)->size(1)->formatResponses(['hits' => [
            'total' => ['value' => 1, 'relation' => 'gte'], 'hits' => [],
        ]]);
        $this->assertSame(1, $result->total());
        $this->assertSame('gte', $result->json('total_relation'));
        $this->assertNull($result->format()['total_pages']);
    }

    public function test_json_reads_scalar_values_without_wrapping_them(): void
    {
        $raw = ['hits' => ['total' => 1, 'hits' => [['_id' => '1', '_score' => null]]]];
        $result = $this->search(new NewProperties)->formatResponses($raw);
        $this->assertSame('1', $result->json('hits.0._id'));
        $this->assertNull($result->json('hits.0._score'));
        $multi = (new SigmieMultiSearchResponse)->searches([new NewQuery($this->connection())])
            ->multiSearchResponseRaw(['responses' => [$raw]]);
        $this->assertSame(1, $multi->json('0.total'));
    }

    public function test_dotted_response_keys_are_read_inside_json_paths(): void
    {
        $raw = ['hits' => ['hits' => [['_id' => '1', 'highlight' => ['items.text' => ['red']]]]],
            'aggregations' => ['dotted.name' => ['value' => 3]]];
        $result = $this->search(new NewProperties)->formatResponses($raw);
        $this->assertSame(['red'], $result->json('hits.0.highlight.items.text'));
        $this->assertSame(3, $result->aggregation('dotted.name.value'));
        $http = new HttpSearch(200, [], json_encode($raw));
        $this->assertSame(3, $http->aggregation('dotted.name.value'));
    }

    public function test_facet_selection_filters_hits_in_either_builder_order(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');
        $props->keyword('color');

        $configured = $this->search($props)->facets('brand color', "brand:'dell'");
        $deferred = (new NewSearch($this->connection()))->index('products')
            ->facets('brand color', "brand:'dell'")->properties($props);

        foreach ([$configured, $deferred] as $search) {
            $body = $search->makeSearch()->toRaw();
            $this->assertStringContainsString('"brand"', json_encode($body['post_filter'] ?? []));
            $this->assertStringContainsString('"dell"', json_encode($body['post_filter'] ?? []));
            $this->assertStringNotContainsString('"dell"', json_encode($body['query']));
            $this->assertStringNotContainsString('"dell"', json_encode($body['aggs']['brand']));
            $this->assertStringContainsString('"dell"', json_encode($body['aggs']['color']));
        }
    }

    public function test_an_invalid_facet_selection_appears_in_response_errors(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');

        $search = $this->search($props)->facets('brand', "brand:'dell' AND brand:'hp'");

        $this->assertStringContainsString('cannot contain', strtolower(
            json_encode($search->formatResponses([])->format()['errors'])
        ));
    }

    public function test_semantic_queries_disappear_when_semantic_search_is_disabled(): void
    {
        $props = new NewProperties;
        $props->text('indexed')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);
        $props->text('exact')->semantic(api: 'embeddings', dimensions: 128, accuracy: 7);
        $search = $this->search($props)->semantic()->queryString('guide', vector: array_fill(0, 128, 0.1));

        $before = $search->makeSearch()->toRaw();
        $this->assertArrayHasKey('knn', $before);
        $this->assertStringContainsString('cosineSimilarity', json_encode($before['query']));

        $after = $search->semantic(false)->makeSearch()->toRaw();
        $this->assertArrayNotHasKey('knn', $after);
        $this->assertStringNotContainsString('cosineSimilarity', json_encode($after['query']));
    }

    public function test_keyword_disabling_excludes_match_all_from_a_vector_only_search(): void
    {
        $props = new NewProperties;
        $props->image('photo')->semantic(api: 'clip', dimensions: 128);

        $fromImage = $this->search($props)->semantic()->disableKeywordSearch()
            ->queryImage('https://example.com/item.jpg', vector: array_fill(0, 128, 0.1))
            ->makeSearch()->toRaw();
        $fromVector = $this->search($props)->semantic()->disableKeywordSearch()
            ->queryString('', vector: array_fill(0, 128, 0.1))
            ->makeSearch()->toRaw();

        foreach ([$fromImage, $fromVector] as $body) {
            $this->assertArrayHasKey('knn', $body);
            $this->assertStringContainsString('match_none', json_encode($body['query']));
        }
    }

    public function test_default_rerank_query_uses_the_first_nonblank_search_input(): void
    {
        $response = $this->search(new NewProperties)->queryString(' ')->queryString('guide')->formatResponses([
            'hits' => ['hits' => [['_id' => 'guide', '_source' => ['title' => 'Guide']]]],
        ]);
        $api = $this->createMock(RerankApi::class);
        $api->expects($this->once())->method('rerank')->with($this->isType('array'), 'guide', 1)
            ->willReturn([['index' => 0, 'score' => 0.9]]);

        $hits = $response->rerank($api, ['title']);

        $this->assertSame(['guide'], array_column($hits, '_id'));
    }

    public function test_a_query_field_scope_can_target_a_semantic_field_outside_default_fields(): void
    {
        $props = new NewProperties;
        $props->text('title');
        $props->text('description')->semantic(api: 'embeddings', dimensions: 128);
        $api = $this->createStub(EmbeddingsApi::class);
        $api->method('maxBatchSize')->willReturn(32);
        $api->method('batchEmbed')->willReturn([['text' => 'guide', 'dims' => 128, 'vector' => array_fill(0, 128, 0.1)]]);

        $body = $this->search($props)->fields(['title'])->semantic()->apis(['embeddings' => $api])
            ->queryString('guide', fields: ['description'])
            ->makeSearch()->toRaw();

        $this->assertStringStartsWith('_embeddings.description.', $body['knn'][0]['field'] ?? '');
    }

    public function test_a_scoped_image_query_does_not_send_an_image_to_a_text_provider(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'text', dimensions: 128);
        $props->image('photo')->semantic(api: 'clip', dimensions: 128);
        $text = $this->createMock(EmbeddingsApi::class);
        $text->expects($this->never())->method('embed')->willReturn(array_fill(0, 128, 0.1));
        $clip = $this->createStub(EmbeddingsApi::class);
        $clip->method('embed')->willReturn(array_fill(0, 128, 0.1));

        $body = $this->search($props)->semantic()->apis(['text' => $text, 'clip' => $clip])
            ->queryImage('https://example.com/item.jpg', fields: ['photo'])
            ->makeSearch()->toRaw();

        $this->assertCount(1, $body['knn']);
        $this->assertStringStartsWith('_embeddings.photo.', $body['knn'][0]['field']);
    }

    public function test_search_uses_an_embedding_provider_named_zero(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: '0', dimensions: 128);
        $vector = [1.0, ...array_fill(0, 127, 0.0)];
        $api = $this->createStub(EmbeddingsApi::class);
        $api->method('maxBatchSize')->willReturn(32);
        $api->method('batchEmbed')->willReturn([['text' => 'guide', 'dims' => 128, 'vector' => $vector]]);

        $body = $this->search($props)->semantic()->apis(['0' => $api])->queryString('guide')
            ->makeSearch()->toRaw();

        $this->assertSame($vector, $body['knn'][0]['query_vector'] ?? null);
    }

    public function test_multi_search_surfaces_a_failed_new_search_item(): void
    {
        $item = ['error' => ['type' => 'index_not_found_exception'], 'status' => 404];
        $batch = new NewMultiSearch($this->respondingConnection(['responses' => [$item]]));
        $batch->newSearch('missing');

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(404);
        $batch->get();
    }

    public function test_rrf_keeps_documents_with_the_same_id_in_different_indices(): void
    {
        $products = new Hit([], '42', 2, 'products');
        $archive = ['_id' => '42', '_index' => 'archive', '_score' => 1];

        $hits = (new RRF)->fuse([[$products, $archive], [$archive]]);

        $this->assertCount(2, $hits);
        $this->assertSame('archive', $hits[0]['_index']);
        $this->assertSame('products', $hits[1]->_index);
    }

    public function test_a_template_uses_the_configured_completion_field(): void
    {
        $props = new NewProperties;
        $props->completion('title');

        $source = (new NewTemplate($this->connection()))->id('products')->properties($props)->get()->source();

        $this->assertStringContainsString('"completion":{"field":"title"', $source);
    }

    public function test_facet_json_lookup_keeps_a_dotted_field_name(): void
    {
        $props = new NewProperties;
        $props->object('specs', fn (NewProperties $specs): Keyword => $specs->keyword('color'));

        $search = $this->search($props)->facets('specs.color');
        $response = $search->formatResponses([
            'aggregations' => [
                'specs.color' => ['specs.color' => ['buckets' => [['key' => 'red', 'doc_count' => 3]]]],
            ],
        ]);

        $this->assertSame(['red' => 3], $response->json('facets.specs.color'));
    }

    public function test_a_numeric_facet_name_survives_response_serialization(): void
    {
        $props = new NewProperties;
        $props->keyword('0');

        $response = $this->search($props)->facets('0')->formatResponses([
            'aggregations' => ['0' => ['0' => ['buckets' => [['key' => 'red', 'doc_count' => 3]]]]],
        ]);

        $this->assertSame(['red' => 3], $response->json('facets.0'));
        $this->assertStringContainsString('"facets":{"0":{"red":3}}', json_encode($response->format()));
    }

    public function test_raw_formatter_still_supports_search_hits(): void
    {
        $raw = ['_id' => '42', '_index' => 'products', '_score' => 1.0, '_source' => ['title' => 'Guide']];
        $connection = $this->respondingConnection(['responses' => [['hits' => ['hits' => [$raw]]]]]);

        $hits = (new NewSearch($connection))->index('products')->formatter(new RawElasticsearchFormat)->hits();

        $this->assertInstanceOf(Hit::class, $hits[0]);
        $this->assertSame('42', $hits[0]->_id);
        $this->assertSame(['title' => 'Guide'], $hits[0]->_source);
    }

    public function test_context_keeps_selected_null_fields(): void
    {
        $hit = new Hit(['title' => 'Guide', 'summary' => null], '42', 1.0);

        $context = (new NewContextComposer)->fields(['title', 'summary', 'unknown'])->compose([$hit]);

        $this->assertSame(['title' => 'Guide', 'summary' => null], json_decode($context, true));
    }

    public function test_empty_multi_search_returns_no_results_without_sending_a_request(): void
    {
        $batch = new NewMultiSearch($this->connection());

        $this->assertSame([], $batch->get());
        $this->assertSame([], $batch->hits());
        $this->assertSame([], $batch->groupedHits());
    }

    /** @test */
    public function lenient_facets_report_both_filter_and_facet_validation_errors(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');

        $empty = ['hits' => ['total' => ['value' => 0], 'hits' => []], 'took' => 1];

        $search = (new NewSearch($this->connection()))
            ->index('products')
            ->properties($props)
            ->facets('brand unknown_facet', 'unknown_filter:bad brand:Apple');

        $errors = $search->formatRespones($empty, $empty)->format()['errors'];
        $this->assertSame([
            "Facet field 'unknown_filter' was not found.",
            'Field unknown_facet does not exist.',
        ], array_column($errors, 'message'));
    }

    /** @test */
    public function standalone_search_surfaces_a_failed_multi_search_item(): void
    {
        $item = ['error' => ['type' => 'index_not_found_exception'], 'status' => 404];
        $connection = $this->respondingConnection(['responses' => [$item]]);

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionCode(404);
        (new NewSearch($connection))->index('missing-index')->get();
    }

    /**
     * @test
     *
     * @dataProvider formatterChoices
     */
    public function search_results_keep_their_own_formatter_and_context(bool $customFormatter): void
    {
        $response = static fn (string $id): array => ['responses' => [['hits' => ['hits' => [
            ['_id' => $id, '_index' => 'products', '_source' => [], '_score' => 1.0],
        ]]]]];
        $connection = $this->respondingConnection($response('first'), $response('second'));
        $props = new NewProperties;
        $props->text('title');
        $props->nested('reviews', function (NewProperties $reviews): void {
            $reviews->text('body');
        });
        $search = (new NewSearch($connection))->index('products')->properties($props);

        if ($customFormatter) {
            $search->formatter(new SigmieSearchResponse($props->get()));
        }

        $first = $search->page(2, 3)->queryString('first')->get();
        $second = $search->page(1, 5)->queryString('second')->innerHits('reviews')->get();
        $search->queryStrings()[0]->setVector([1.0]);

        $this->assertSame('first', $first->hits()[0]->_id);
        $this->assertSame(2, $first->format()['page']);
        $this->assertSame(3, $first->format()['per_page']);
        $this->assertSame(['first'], $first->format()['query_strings']);
        $this->assertFalse($first->getContext()->queryStrings[0]->hasVector());
        $this->assertFalse($first->getContext()->innerHits->requested());
        $this->assertSame('second', $second->hits()[0]->_id);
        $this->assertTrue($second->getContext()->innerHits->requested());
    }

    public static function formatterChoices(): array
    {
        return ['default' => [false], 'custom' => [true]];
    }

    /** @test */
    public function collapsed_page_keeps_the_document_total_and_reports_unknown_pages(): void
    {
        $connection = $this->respondingConnection(['responses' => [[
            'hits' => ['total' => ['value' => 6, 'relation' => 'eq'], 'hits' => [['_id' => 'product-a']]],
        ]]]);
        $props = new NewProperties;
        $props->keyword('product_id');

        $response = (new NewSearch($connection))
            ->index('products')
            ->properties($props)
            ->uniqueBy('product_id')
            ->page(1, 1)
            ->get();

        $this->assertSame(6, $response->total());
        $this->assertSame(6, $response->format()['total']);
        $this->assertNull($response->format()['total_pages']);
    }

    /**
     * @test
     *
     * @dataProvider incompletePitPages
     */
    public function a_partial_pit_page_throws_and_closes_the_latest_pit(array $partial): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->expects($this->exactly(4))->method('__invoke')->willReturnCallback(
            function (ElasticsearchRequest $request) use ($partial): ElasticsearchResponseContract {
                $body = json_decode((string) $request->getBody(), true);
                $response = match ($request->getMethod()) {
                    'DELETE' => ['succeeded' => true],
                    default => match ($body['pit']['id'] ?? null) {
                        null => ['id' => 'opened'],
                        'opened' => ['hits' => ['hits' => [['_id' => 'complete', '_index' => 'products', 'sort' => [1]]]]],
                        default => $partial,
                    },
                };

                if ($request->getMethod() === 'DELETE') {
                    $this->assertSame('updated', $body['id']);
                } elseif (($body['pit']['id'] ?? null) === 'opened') {
                    $response['pit_id'] = 'next';
                }

                return $request->response(new PsrResponse(200, [], json_encode($response)));
            }
        );
        $iterator = (new RawQuery($connection, 'products', []))->chunk(1)->lazy();
        $this->assertSame('complete', $iterator->current()->_id);

        $this->expectException(ElasticsearchException::class);
        $iterator->next();
    }

    public static function incompletePitPages(): array
    {
        return [
            'timeout with a hit' => [['pit_id' => 'updated', 'timed_out' => true, 'hits' => [
                'hits' => [['_id' => 'partial', '_index' => 'products', 'sort' => [2]]],
            ]]],
            'failed shard with no hits' => [['pit_id' => 'updated', '_shards' => [
                'total' => 2, 'successful' => 1, 'failed' => 1,
            ], 'hits' => ['hits' => []]]],
            'unassigned shard' => [['pit_id' => 'updated', '_shards' => [
                'total' => 2, 'successful' => 1, 'failed' => 0,
            ], 'hits' => ['hits' => []]]],
        ];
    }

    /** @test */
    public function a_lazy_vector_window_uses_the_chunk_and_leaves_page_settings_intact(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $windows = [];
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request) use (&$windows): ElasticsearchResponseContract {
            $body = json_decode((string) $request->getBody(), true);
            if (isset($body['pit'])) {
                $windows[] = [$body['size'], $body['knn'][0]['k']];
            }

            return $request->response(new PsrResponse(200, [], '{"id":"pit-1","hits":{"hits":[]}}'));
        });
        $search = (new NewSearch($connection))->index('products')->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('quantum', dimension: 128, vector: array_fill(0, 128, 0.1))
            ->chunk(7);

        iterator_to_array($search->page(3, 2)->lazy());
        iterator_to_array($search->size(4)->lazy());

        $this->assertSame([[7, 7], [7, 7]], $windows);
        $page = $search->makeSearch()->toRaw();
        $this->assertSame(4, $page['from']);
        $this->assertSame(4, $page['size']);
        $this->assertGreaterThanOrEqual(8, $page['knn'][0]['k']);
    }

    private function respondingConnection(array ...$responses): ElasticsearchConnection
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->method('__invoke')->willReturnCallback(
            function (ElasticsearchRequest $request) use (&$responses): ElasticsearchResponseContract {
                return $request->response(new PsrResponse(200, [], json_encode(array_shift($responses))));
            }
        );

        return $connection;
    }

    /** @test */
    public function rrf_uses_list_position_for_keyed_hits(): void
    {
        $hits = (new RRF)->fuse([[
            'first' => ['_id' => 'a'],
            20 => ['_id' => 'b'],
        ]]);

        $this->assertSame([1 / 61, 1 / 62], array_column($hits, '_score'));
    }

    /** @test */
    public function raw_reranked_hits_keep_nested_matches(): void
    {
        $matches = ['comments' => ['total' => 2, 'items' => [['body' => 'match']]]];
        $api = $this->createStub(RerankApi::class);
        $api->method('rerank')->willReturn([['index' => 0, 'score' => 0.9]]);
        $rerank = (new NewRerank($api))->query('match');

        foreach ([$matches, (object) $matches] as $rawMatches) {
            [$hit] = $rerank->rerank([['_id' => 'a', '_matches' => $rawMatches]]);
            $this->assertSame([['body' => 'match']], $hit->matches('comments'));
            $this->assertSame(2, $hit->matchesTotal('comments'));
        }
    }

    /** @test */
    public function inner_hits_count_overlapping_child_clauses_once(): void
    {
        $properties = new NewProperties;
        $properties->nested('orders', function (NewProperties $order): void {
            $order->nested('items', fn (NewProperties $item): Keyword => $item->keyword('sku'));
        });
        $inner = new InnerHits;
        $inner->request(['orders.items'], 2);
        $inner->resolve($properties->get());

        $child = ['_nested' => [
            'field' => 'orders', 'offset' => 0,
            '_nested' => ['field' => 'items', 'offset' => 1],
        ]];
        $block = ['hits' => ['total' => ['value' => 1], 'hits' => [$child]]];
        $direct = [$inner->clause('orders.items')['name'] => $block];
        $parents = [$inner->clause('orders')['name'] => ['hits' => ['hits' => [[
            '_nested' => ['field' => 'orders', 'offset' => 0],
            'inner_hits' => [$inner->clause('orders.items')['name'] => $block],
        ]]]]];

        foreach ([$direct + $parents, $parents + $direct] as $clauses) {
            $match = $inner->matches($clauses)['orders.items'];
            $this->assertSame(1, $match['total']);
            $this->assertCount(1, $match['items']);
        }
    }

    /** @test */
    public function inner_hits_fetch_parents_beyond_the_parent_display_size(): void
    {
        $properties = new NewProperties;
        $properties->nested('orders', function (NewProperties $order): void {
            $order->nested('items', fn (NewProperties $item): Keyword => $item->keyword('sku'));
        });
        $inner = new InnerHits;
        $inner->request(['orders'], 1);
        $inner->request(['orders.items'], 2);
        $inner->resolve($properties->get());

        $clause = $inner->clause('orders');
        $child = $inner->clause('orders.items');
        $parents = [];
        foreach ([0, 1] as $offset) {
            $position = ['field' => 'orders', 'offset' => $offset];
            $parents[] = [
                '_nested' => $position,
                'inner_hits' => [$child['name'] => ['hits' => ['hits' => [[
                    '_nested' => $position + ['_nested' => ['field' => 'items', 'offset' => 0]],
                ]]]]],
            ];
        }

        $matches = $inner->matches([$clause['name'] => ['hits' => [
            'total' => 2, 'hits' => array_slice($parents, 0, $clause['size']),
        ]]]);
        $this->assertCount(1, $matches['orders']['items']);
        $this->assertCount(2, $matches['orders.items']['items']);
    }

    /** @test */
    public function inner_hits_read_integer_totals_for_capped_blocks(): void
    {
        $properties = new NewProperties;
        $properties->nested('orders', fn (NewProperties $order): Keyword => $order->keyword('sku'));

        $inner = new InnerHits;
        $inner->request(['orders'], 1);
        $inner->resolve($properties->get());

        $matches = $inner->matches([$inner->clause('orders')['name'] => ['hits' => [
            'total' => 8,
            'hits' => [['_nested' => ['field' => 'orders', 'offset' => 3]]],
        ]]]);

        $this->assertSame(8, $matches['orders']['total']);
    }

    /** @test */
    public function inner_hits_preserve_hashes_in_nested_paths(): void
    {
        $properties = new NewProperties;
        $properties->nested('orders#1', fn (NewProperties $order): Keyword => $order->keyword('sku'));

        $item = $this->innerHit($properties, 'orders#1', ['field' => 'orders#1', 'offset' => 2], ['sku' => 'x']);

        $this->assertSame(['_offset' => 2, 'sku' => 'x'], $item);
    }

    /** @test */
    public function inner_hits_encode_numeric_parent_names_as_json_keys(): void
    {
        $properties = new NewProperties;
        $properties->nested('0', function (NewProperties $order): void {
            $order->nested('items', fn (NewProperties $item): Keyword => $item->keyword('sku'));
        });
        $item = $this->innerHit($properties, '0.items', [
            'field' => '0', 'offset' => 2, '_nested' => ['field' => 'items', 'offset' => 1],
        ], []);

        $this->assertSame('{"0":2}', json_encode($item['_parents']));
    }

    /**
     * @test
     */
    public function format_counts_the_last_partial_page(): void
    {
        $second = $this->formatPage(from: 10, size: 10, total: 11);
        $first = $this->formatPage(from: 0, size: 10, total: 11);

        $this->assertSame(2, $second['page']);
        $this->assertSame(2, $second['total_pages']);
        $this->assertSame(1, $first['page']);
        $this->assertSame(2, $first['total_pages']);
    }

    /**
     * @test
     */
    public function rrf_keeps_hit_id_zero(): void
    {
        $fused = (new RRF)->fuse([[
            ['_id' => '0', '_score' => 3],
            ['_id' => '', '_score' => 3],
            ['_id' => 'a', '_score' => 3],
        ]]);

        $this->assertSame(['0', 'a'], array_column($fused, '_id'));
    }

    /**
     * @test
     */
    public function rrf_writes_the_fusion_score_on_array_and_object_hits(): void
    {
        $object = new Hit(['name' => 'object'], 'object', 9.0);

        $fused = (new RRF(rankConstant: 60, topK: 10))->fuse([[
            ['_id' => 'array', '_score' => 8.0],
            $object,
        ]]);

        $this->assertSame(1.0 / 61, $fused[0]['_score']);
        $this->assertSame($object, $fused[1]);
        $this->assertSame(1.0 / 62, $object->_score);
    }

    /**
     * @test
     */
    public function rerank_calls_the_api_for_query_zero(): void
    {
        $api = new class implements RerankApi
        {
            public int $calls = 0;

            public function rerank(array $newIndexes, string $query, ?int $topK = null): array
            {
                $this->calls++;

                return [['index' => 0, 'score' => 0.5]];
            }
        };

        $hits = [['_id' => 'a', '_index' => 'products', '_score' => 1.0, '_source' => ['title' => 'A']]];

        $reranked = (new NewRerank($api))->query('0')->fields(['title'])->rerank($hits);
        (new NewRerank($api))->query('00')->fields(['title'])->rerank($hits);
        (new NewRerank($api))->query('')->fields(['title'])->rerank($hits);

        $this->assertSame(2, $api->calls);
        $this->assertInstanceOf(RerankedHit::class, $reranked[0]);
    }

    /**
     * @test
     */
    public function autocomplete_prefix_zero_is_sent(): void
    {
        $properties = new NewProperties;
        $properties->completion('title');

        $prefixed = $this->search($properties)->autocompletePrefix('0')->makeSearch()->toRaw();
        $empty = $this->search($properties)->autocompletePrefix('')->makeSearch()->toRaw();

        $this->assertSame('0', $prefixed['suggest']['autocompletion']['prefix']);
        $this->assertArrayNotHasKey('suggest', $empty);
    }

    /**
     * @test
     */
    public function a_completion_field_is_not_a_match_query(): void
    {
        $properties = new NewProperties;
        $properties->completion('title');

        $body = $this->search($properties)->queryString('star')->makeSearch()->toRaw();

        $this->assertSame([], $properties->get()->get('title')->queries('star'));
        $this->assertStringNotContainsString('"match":{"title"', json_encode($body['query']));
    }

    /**
     * @test
     */
    public function json_reads_a_facet_map(): void
    {
        $properties = new NewProperties;
        $properties->keyword('brand');

        $raw = [
            'took' => 1,
            'hits' => ['total' => ['value' => 8, 'relation' => 'eq'], 'hits' => []],
            'aggregations' => [
                'brand' => [
                    'doc_count' => 8,
                    'brand' => [
                        'buckets' => [
                            ['key' => 'Apple', 'doc_count' => 5],
                            ['key' => 'Dell', 'doc_count' => 3],
                        ],
                    ],
                ],
            ],
        ];

        $response = (new SigmieSearchResponse($properties->get()))
            ->queryResponseRaw($raw)
            ->facetsResponseRaw($raw)
            ->context(new SearchContext(facetFields: ['brand']))
            ->errors([]);

        $this->assertSame(['Apple' => 5, 'Dell' => 3], $response->json('facets.brand'));
        $this->assertSame(5, $response->json('facets')['brand']['Apple']);
    }

    /**
     * @test
     */
    public function a_multi_search_keeps_semantic_mode(): void
    {
        $search = (new NewSearch($this->connection()))
            ->index('articles')
            ->semantic()
            ->queryString('return policy');

        $hit = [
            'took' => 2,
            'hits' => [
                'total' => ['value' => 1, 'relation' => 'eq'],
                'hits' => [[
                    '_id' => 'policy',
                    '_index' => 'articles',
                    '_score' => 1.0,
                    '_source' => ['title' => 'Returns'],
                ]],
            ],
        ];

        $multi = (new SigmieMultiSearchResponse)
            ->multiSearchResponseRaw(['responses' => [$hit]])
            ->searches([$search])
            ->format();

        $this->assertTrue($search->formatRespones($hit, $hit)->format()['semantic']);
        $this->assertTrue($multi[0]['semantic']);
    }

    /**
     * @test
     */
    public function inner_hits_parent_path_is_not_repeated(): void
    {
        $properties = new NewProperties;
        $properties->nested('orders', function (NewProperties $properties): void {
            $properties->keyword('number');
            $properties->nested('items', function (NewProperties $properties): void {
                $properties->keyword('sku');
                $properties->nested('lines', function (NewProperties $properties): void {
                    $properties->keyword('code');
                });
            });
        });

        $twoLevel = $this->innerHit($properties, 'orders.items', [
            'field' => 'orders',
            'offset' => 0,
            '_nested' => [
                'field' => 'orders.items',
                'offset' => 1,
            ],
        ], ['sku' => 'y']);

        $threeLevel = $this->innerHit($properties, 'orders.items.lines', [
            'field' => 'orders',
            'offset' => 1,
            '_nested' => [
                'field' => 'orders.items',
                'offset' => 0,
                '_nested' => [
                    'field' => 'orders.items.lines',
                    'offset' => 3,
                ],
            ],
        ], ['code' => 'c']);

        $this->assertSame(
            ['_offset' => 1, '_parents' => ['orders' => 0], 'sku' => 'y'],
            $twoLevel,
        );
        $this->assertSame(
            ['_offset' => 3, '_parents' => ['orders' => 1, 'orders.items' => 0], 'code' => 'c'],
            $threeLevel,
        );
    }

    /**
     * @test
     */
    public function mmr_reads_an_accuracy_7_vector(): void
    {
        $flat = fn (array $vector): array => ['vec' => $vector];
        $wrapped = fn (array $vector): array => ['vec' => [['vector' => $vector]]];

        $this->assertSame('same', $this->rank($flat([1, 0]), $flat([0, 1])));
        $this->assertSame('same', $this->rank($wrapped([1, 0]), $wrapped([0, 1])));
        $this->assertSame('same', $this->rank([['vector' => [1, 0]]], [['vector' => [0, 1]]]));
    }

    /**
     * @test
     */
    public function autocomplete_pipeline_description_zero_is_sent(): void
    {
        $raw = (new NewPipeline($this->connection(), 'products'))
            ->description('0')
            ->toRaw();

        $this->assertSame('0', $raw['description']);
    }

    /**
     * @test
     */
    public function semantic_score_multiplier_scales_the_vector_clause(): void
    {
        $vector = array_fill(0, 128, 0.1);
        $rawFor = function (int $accuracy) use ($vector): array {
            $props = new NewProperties;
            $props->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: $accuracy);

            return (new NewSearch($this->connection()))
                ->index('articles')
                ->properties($props)
                ->semantic()
                ->textScoreMultiplier(1.0)
                ->semanticScoreMultiplier(2.0)
                ->queryString('quantum computing', weight: 1.0, dimension: 128, vector: $vector)
                ->makeSearch()
                ->toRaw();
        };

        $accuracy3 = json_encode($rawFor(3));
        $accuracy7 = json_encode($rawFor(7));

        $this->assertStringContainsString('"boost":2', $accuracy3);
        $this->assertStringNotContainsString('cosineSimilarity', $accuracy3);
        $this->assertStringContainsString('cosineSimilarity', $accuracy7);
        $this->assertStringContainsString('"boost":2', $accuracy7);
    }

    /**
     * @test
     */
    public function facets_keep_the_query_filter_errors(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');

        $empty = ['hits' => ['total' => ['value' => 0], 'hits' => []], 'took' => 1];

        $search = (new NewSearch($this->connection()))
            ->index('products')
            ->properties($props)
            ->queryString('laptop')
            ->filters('nonexistent:"x"');

        $search->facets('brand', "brand:'Apple'");

        $errors = $search->formatRespones($empty, $empty)->format()['errors'];

        $this->assertSame('Field nonexistent does not exist.', $errors[0]['message']);
    }

    /**
     * @test
     */
    public function the_second_properties_call_is_the_field_list(): void
    {
        $first = new NewProperties;
        $first->keyword('name');

        $second = new NewProperties;
        $second->keyword('title');

        $raw = (new NewSearch($this->connection()))
            ->index('products')
            ->properties($first)
            ->properties($second)
            ->queryString('hello')
            ->makeSearch()
            ->toRaw();

        $encoded = json_encode($raw);

        $this->assertStringContainsString('title', $encoded);
        $this->assertStringNotContainsString('"name"', $encoded);
    }

    /**
     * @test
     */
    public function inner_hits_keep_a_negative_score_and_the_nested_offset(): void
    {
        $props = new NewProperties;
        $props->nested('reviews', function (NewProperties $props): void {
            $props->text('comment');
        });

        $inner = new InnerHits;
        $inner->request(['reviews'], 10);
        $inner->resolve($props->get());

        $name = $inner->clause('reviews')['name'];
        $hit = fn (float $score, array $source): array => [
            $name => [
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [[
                        '_score' => $score,
                        '_source' => $source,
                        '_nested' => ['field' => 'reviews', 'offset' => 0],
                    ]],
                ],
            ],
        ];

        $negative = $inner->matches($hit(-2, ['comment' => 'bad']))['reviews'];
        $offset = $inner->matches($hit(1, ['comment' => 'ok', '_offset' => 99]))['reviews']['items'][0];

        $this->assertSame(1, $negative['total']);
        $this->assertSame('bad', $negative['items'][0]['comment']);
        $this->assertSame(0, $offset['_offset']);
        $this->assertSame('ok', $offset['comment']);
    }

    /**
     * @test
     */
    public function an_inner_hit_field_named_one_stays(): void
    {
        $props = new NewProperties;
        $props->nested('reviews', function (NewProperties $props): void {
            $props->keyword('1');
        });

        $inner = new InnerHits;
        $inner->request(['reviews'], 10);
        $inner->resolve($props->get());

        $matches = $inner->matches([
            $inner->clause('reviews')['name'] => [
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [[
                        '_score' => 1,
                        '_source' => ['1' => 'kept', '2' => 'also', '_offset' => 99],
                        '_nested' => ['field' => 'reviews', 'offset' => 0],
                    ]],
                ],
            ],
        ]);

        $item = $matches['reviews']['items'][0];
        $json = json_encode($item);

        $this->assertStringContainsString('"1":"kept"', $json);
        $this->assertStringContainsString('"2":"also"', $json);
        $this->assertSame(0, $item['_offset']);
    }

    /**
     * @test
     */
    public function a_matches_path_named_zero_stays_an_object(): void
    {
        $props = new NewProperties;
        $props->nested('0', function (NewProperties $props): void {
            $props->keyword('sku');
        });
        $props->nested('1', function (NewProperties $props): void {
            $props->keyword('sku');
        });
        $props->nested('reviews', function (NewProperties $props): void {
            $props->keyword('sku');
        });
        $built = $props->get();

        $block = static fn (string $path, string $sku): array => [
            'hits' => [
                'total' => ['value' => 1],
                'hits' => [[
                    '_score' => 1,
                    '_source' => ['sku' => $sku],
                    '_nested' => ['field' => $path, 'offset' => 0],
                ]],
            ],
        ];

        $inner = new InnerHits;
        $inner->request(['0', '1'], 2);
        $inner->resolve($built);

        $zero = $inner->clause('0')['name'];
        $one = $inner->clause('1')['name'];
        $json = json_encode($inner->formatRawHit([
            'inner_hits' => [
                $zero => $block('0', 'A'),
                $one => $block('1', 'B'),
            ],
        ])['_matches']);

        $this->assertStringContainsString('"0":', $json);
        $this->assertStringContainsString('"sku":"A"', $json);
        $this->assertStringContainsString('"1":', $json);
        $this->assertStringContainsString('"sku":"B"', $json);

        $fromJson = new Hit([], 'doc-1', 1.0, 'products', null, InnerHits::matchList(
            $inner->formatRawHit([
                'inner_hits' => [
                    $zero => $block('0', 'A'),
                    $one => $block('1', 'B'),
                ],
            ])['_matches'],
        ));
        $this->assertSame(1, $fromJson->matchesTotal('0'));
        $this->assertSame('A', $fromJson->matches('0')[0]['sku']);
        $this->assertSame('B', $fromJson->matches('1')[0]['sku']);

        $reviews = new InnerHits;
        $reviews->request(['reviews'], 2);
        $reviews->resolve($built);

        $formatted = $reviews->formatRawHit([
            'inner_hits' => [$reviews->clause('reviews')['name'] => $block('reviews', 'A')],
        ]);

        $this->assertSame(1, $formatted['_matches']['reviews']['total']);
        $this->assertStringContainsString('"reviews":', json_encode($formatted['_matches']));

        $hit = new Hit([], 'doc-1', 1.0, 'products', null, [
            '0' => ['total' => 1, 'items' => [['sku' => 'A']]],
            '1' => ['total' => 0, 'items' => []],
        ]);

        $this->assertSame(1, $hit->matchesTotal('0'));
        $this->assertSame([], $hit->matches('1'));
        $this->assertStringContainsString('"_matches":{"0":', json_encode($hit->toArray()));

        $response = new HttpSearch(200, [], json_encode([
            'hits' => ['hits' => []],
        ]));
        $replaced = $response->replaceHits([[
            '_id' => 'doc-1',
            '_score' => 1.0,
            '_index' => 'products',
            '_source' => [],
            '_matches' => object_map([
                '0' => ['total' => 1, 'items' => [['sku' => 'A']]],
                '1' => ['total' => 0, 'items' => []],
            ]),
        ]]);

        $this->assertSame('A', $replaced[0]->matches('0')[0]['sku']);
        $this->assertStringContainsString('"_matches":{"0":', json_encode($replaced[0]->toArray()));
    }

    /**
     * @test
     */
    public function a_rerank_payload_reads_a_dotted_field(): void
    {
        $hit = new Hit([
            'author' => ['name' => 'Ada'],
            'comments' => [
                ['body' => 'First nested body'],
                ['body' => 'Second nested body'],
            ],
            'title' => 'Matrix',
            'author.name' => 'Literal',
        ], '1', 1.0, 'docs');

        $parsed = Yaml::parse(NewRerank::documentPayloads(
            [$hit],
            ['title', 'author.name', 'comments.body'],
        )[0]);

        $this->assertSame('Matrix', $parsed['title']);
        $this->assertSame('Literal', $parsed['author.name']);
        $this->assertSame(['First nested body', 'Second nested body'], $parsed['comments.body']);

        $dotted = Yaml::parse(NewRerank::documentPayloads(
            [new Hit([
                'author' => ['name' => 'Ada'],
                'comments' => [
                    ['body' => 'First nested body'],
                    ['body' => 'Second nested body'],
                ],
            ], '1', 1.0, 'docs')],
            ['author.name', 'comments.body'],
        )[0]);

        $this->assertSame('Ada', $dotted['author.name']);
        $this->assertSame(['First nested body', 'Second nested body'], $dotted['comments.body']);
    }

    /**
     * @test
     */
    public function mmr_diversifies_a_field_named_zero(): void
    {
        $hit = fn (string $id, array $vector): array => [
            '_id' => $id,
            '_source' => ['_embeddings' => ['0' => $vector]],
        ];

        $ranked = (new MMR(1.0))->diversify(
            [$hit('other', [0, 1]), $hit('same', [1, 0])],
            [$hit('seed', [1, 0])],
            '0',
            2,
        );

        $this->assertSame(['same', 'other'], array_column($ranked, '_id'));
    }

    /**
     * @test
     */
    public function a_negative_mmr_similarity_enters_the_formula(): void
    {
        $hit = fn (string $id, array $vector): array => [
            '_id' => $id,
            '_source' => ['_embeddings' => ['title' => $vector]],
        ];

        $ranked = (new MMR(0.2))->diversify(
            [
                $hit('same', [1.0, 0.0]),
                $hit('anti', [-1.0, 0.0]),
                $hit('slight', [0.2, sqrt(0.96)]),
            ],
            [$hit('seed', [1.0, 0.0])],
            'title',
            2,
        );

        $this->assertSame(['same', 'anti'], array_column($ranked, '_id'));
    }

    /**
     * @test
     */
    public function an_mmr_score_uses_every_stored_vector(): void
    {
        $stored = fn (string $id, array $vectors): array => [
            '_id' => $id,
            '_source' => [
                '_embeddings' => [
                    'title' => array_map(
                        fn (array $vector): array => ['vector' => $vector],
                        $vectors,
                    ),
                ],
            ],
        ];

        $ranked = (new MMR(1.0))->diversify(
            [
                $stored('second-matches', [[0.0, 1.0], [1.0, 0.0]]),
                $stored('mediocre', [[0.6, 0.8]]),
            ],
            [[
                '_id' => 'seed',
                '_source' => ['_embeddings' => ['title' => [1.0, 0.0]]],
            ]],
            'title',
            1,
        );

        $this->assertSame('second-matches', $ranked[0]['_id']);
    }

    /** @test */
    public function regular_filter_after_facet_keeps_its_term(): void
    {
        $properties = new NewProperties;
        $brand = $properties->keyword('brand');
        $parser = new FilterParser($properties);

        $facet = $parser->facetFilter($brand, "brand:'Nike'")->toRaw();
        $this->assertArrayHasKey('match_all', $facet['bool']['must'][0]);

        $query = $parser->parse("brand:'Nike'")->toRaw();
        $this->assertArrayHasKey('term', $query['bool']['must'][0]);
    }

    /** @test */
    public function logical_operator_names_are_valid_filter_fields(): void
    {
        $properties = new NewProperties;
        $properties->keyword('or');

        $query = (new FilterParser($properties))->parse("or:'first' OR or : 'second'")->toRaw();
        $clauses = $query['bool']['should'];

        $this->assertSame('first', $clauses[0]['bool']['must'][0]['term']['or']['value']);
        $this->assertSame('second', $clauses[1]['bool']['must'][0]['term']['or']['value']);
    }

    /** @test */
    public function sorts_keep_nested_path_zero(): void
    {
        $properties = new NewProperties;
        $properties->nested('0', function (NewProperties $nested): void {
            $nested->keyword('name');
            $nested->geoPoint('location');
        });

        $sorts = (new SortParser($properties))->parse('0.name 0.location[0,0]:km:desc');
        $this->assertSame(['nested' => ['path' => '0'], 'order' => 'asc'], $sorts[0]['0.name']);
        $this->assertSame('0', $sorts[1]['_geo_distance']['nested']['path']);
    }

    public function test_geo_sort_resolves_a_relative_field_to_its_full_path(): void
    {
        $properties = new NewProperties;
        $properties->nested('stores', fn (NewProperties $store): GeoPoint => $store->geoPoint('location'));

        $storeFields = $properties->get()->get('stores')->getProperties();
        [$sort] = (new SortParser($storeFields))->parse('location[51,13]:km:asc');

        $this->assertSame(['lat' => 51.0, 'lon' => 13.0], $sort['_geo_distance']['stores.location'] ?? null);
        $this->assertSame(['path' => 'stores'], $sort['_geo_distance']['nested']);
    }

    public function test_geo_sort_on_a_field_named_nested_uses_a_coordinate_array(): void
    {
        $properties = new NewProperties;
        $properties->geoPoint('nested');
        [$sort] = (new SortParser($properties))->parse('nested[51,13]:km:asc');

        $this->assertSame([13.0, 51.0], $sort['_geo_distance']['nested']);
        $this->assertSame('km', $sort['_geo_distance']['unit']);
    }

    /**
     * @test
     *
     * @dataProvider malformedSortClauses
     */
    public function malformed_sort_clause_is_rejected(string $clause): void
    {
        $properties = new NewProperties;
        $properties->keyword('brand');
        $properties->geoPoint('location');

        $parser = new SortParser($properties, false);

        $this->assertSame(['_doc', ['brand' => 'desc']], $parser->parse(sprintf('_doc %s brand:desc', $clause)));
        $this->assertCount(1, $parser->errors());

        $this->expectException(ParseException::class);
        $parser->throwOnError()->parse($clause);
    }

    public static function malformedSortClauses(): array
    {
        return [
            'unknown direction' => ['brand:sideways'],
            'empty direction' => ['brand:'],
            'extra field option' => ['brand:asc:extra'],
            'extra geo option' => ['location[1,2]:km:asc:extra'],
            'geo prefix' => ['!location[1,2]:km:asc'],
        ];
    }

    public function test_template_text_embedding_searches_in_the_field_nested_scope(): void
    {
        $properties = new NewProperties;
        $properties->nested('contact', function (NewProperties $fields): void {
            $fields->text('name')->semantic(api: 'test-embeddings');
        });

        $source = (new NewTemplate($this->connection()))
            ->id('products')->properties($properties)->fields(['contact.name'])->semantic()->get()->source();

        $semanticQuery = explode('"script_score":', $source, 2)[0];
        $this->assertStringContainsString('"nested":{"path":"contact"', $semanticQuery);
    }

    public function test_template_semantic_search_respects_the_field_scope(): void
    {
        $properties = new NewProperties;
        $properties->text('title')->semantic(api: 'test-embeddings');
        $properties->text('notes')->semantic(api: 'test-embeddings');

        $source = (new NewTemplate($this->connection()))
            ->id('products')->properties($properties)->fields(['title'])->semantic()->get()->source();

        $this->assertStringContainsString('_embeddings_title', $source);
        $this->assertStringNotContainsString('_embeddings_notes', $source);
    }

    /**
     * @test
     */
    public function a_template_field_named_zero_stays_in_the_sort_and_facets(): void
    {
        $properties = new NewProperties;
        $properties->number('0');
        $properties->number('rating');
        $properties->keyword('0');

        $source = (new NewTemplate($this->connection()))
            ->id('products')
            ->properties($properties)
            ->fields([])
            ->autocomplete(false)
            ->sort('0:desc rating:asc')
            ->facets('0')
            ->get()
            ->source();

        $block = function (string $tag) use ($source): string {
            $start = strpos($source, '{{^'.$tag.'}}');
            $end = strpos($source, '{{/'.$tag.'}}');

            return substr($source, $start + strlen('{{^'.$tag.'}}'), $end - $start - strlen('{{^'.$tag.'}}'));
        };

        $this->assertStringContainsString('{"0":"desc"}', $block('sort'));
        $this->assertStringContainsString('{"rating":"asc"}', $block('sort'));
        $this->assertStringStartsWith('{"0":', $block('facets'));
    }

    /**
     * @test
     */
    public function a_search_sort_named_zero_stays_in_the_clause(): void
    {
        $props = new NewProperties;
        $props->keyword('0');
        $props->keyword('brand');

        $sort = json_encode($this->search($props)->queryString('')->sort('0:desc brand:asc')->makeSearch()->toRaw()['sort']);

        $this->assertSame('[{"0":"desc"},{"brand":"asc"}]', $sort);

        $querySort = json_encode((new Search($this->connection()))
            ->sort([['0' => 'desc'], ['brand' => 'asc']])
            ->toRaw()['sort']);
        $stringSort = json_encode((new Search($this->connection()))
            ->filterParserProperties($props->get())
            ->sortString('0:desc brand:asc')
            ->toRaw()['sort']);
        $newQuerySort = json_encode((new NewQuery($this->connection(), 'products'))
            ->properties($props)
            ->sortString('0:desc brand:asc')
            ->toMultiSearch()[1]['sort']);

        $this->assertSame('[{"0":"desc"},{"brand":"asc"}]', $querySort);
        $this->assertSame('[{"0":"desc"},{"brand":"asc"}]', $stringSort);
        $this->assertSame('[{"0":"desc"},{"brand":"asc"}]', $newQuerySort);
    }

    public function test_sparse_sort_clauses_keep_their_order_and_numeric_field_names(): void
    {
        $query = (new NewQuery($this->connection(), 'products'))
            ->sort([2 => ['0' => 'desc'], 5 => 'brand', 9 => ['1' => ['order' => 'asc']]]);

        $this->assertSame('[{"0":"desc"},"brand",{"1":{"order":"asc"}}]', json_encode($query->toMultiSearch()[1]['sort']));

        $direct = $query->sort(['1' => ['order' => 'desc']]);
        $this->assertSame('[{"1":{"order":"desc"}}]', json_encode($direct->toMultiSearch()[1]['sort']));

        $emptyOptions = $query->sort(['1' => new stdClass]);
        $this->assertSame('[{"1":{}}]', json_encode($emptyOptions->toMultiSearch()[1]['sort']));
    }

    /**
     * @test
     */
    public function a_raw_query_sort_named_zero_stays_on_the_multi_search_line(): void
    {
        $raw = new RawQuery($this->offlineMultiSearchConnection(), 'orders', [
            'query' => ['match_all' => (object) []],
            'sort' => [
                ['0' => 'asc'],
                ['processed_at' => 'asc'],
            ],
        ]);

        $sort = json_encode($raw->toMultiSearch()[1]['sort']);

        $this->assertSame('[{"0":"asc"},{"processed_at":"asc"}]', $sort);
    }

    /**
     * @test
     */
    public function a_facet_named_zero_stays_an_object(): void
    {
        $props = new NewProperties;
        $props->keyword('0');

        $facet = json_encode($this->search($props)->queryString('')->facets('0')->makeSearch()->toRaw()['aggs']);

        $this->assertStringStartsWith('{"0":', $facet);
    }

    /**
     * @test
     */
    public function a_search_aggregation_named_one_keeps_its_name(): void
    {
        $props = new NewProperties;
        $props->keyword('1');

        $named = json_encode($this->search($props)->queryString('')->aggregate(function (Aggs $aggs): void {
            $aggs->max('1', '1');
        })->makeSearch()->toRaw()['aggs']);

        $this->assertStringContainsString('"1":{"max":{"field":"1"}}', $named);
    }

    /**
     * @test
     */
    public function a_highlight_field_named_zero_stays_in_the_fields(): void
    {
        $props = new NewProperties;
        $props->keyword('0');

        $highlight = json_encode($this->search($props)->queryString('')->highlighting(['0'], '<em>', '</em>')->makeSearch()->toRaw()['highlight']);
        $direct = json_encode((new Search($this->connection()))->highlight([
            'fields' => ['0' => ['type' => 'plain']],
        ])->toRaw()['highlight']);

        $this->assertStringContainsString('"fields":{"0":', $highlight);
        $this->assertStringContainsString('"fields":{"0":', $direct);
    }

    /**
     * @test
     */
    public function a_template_filter_keeps_a_dollar_value(): void
    {
        $props = new NewProperties;
        $props->keyword('category');
        $props->text('title');

        $source = (new NewTemplate($this->connection()))
            ->id('products')
            ->properties($props)
            ->fields(['title'])
            ->filters('category:"a$1b"')
            ->autocomplete(false)
            ->get()
            ->source();

        $start = strpos($source, '{{^filters}}');
        $end = strpos($source, '{{/filters}}');
        $default = substr($source, $start + strlen('{{^filters}}'), $end - $start - strlen('{{^filters}}'));

        $this->assertSame('a$1b', json_decode($default, true)['bool']['must'][0]['term']['category']['value']);
    }

    /**
     * @test
     */
    public function hits_accept_a_null_source(): void
    {
        $formatter = (new SigmieSearchResponse(new Properties))
            ->context(new SearchContext)
            ->errors([])
            ->queryResponseRaw([
                'hits' => [
                    'total' => ['value' => 1, 'relation' => 'eq'],
                    'hits' => [[
                        '_id' => '1',
                        '_index' => 'movies',
                        '_score' => 1.0,
                        '_source' => null,
                    ]],
                ],
            ]);

        $hits = $formatter->hits();

        $this->assertCount(1, $hits);
        $this->assertSame('1', $hits[0]->_id);
        $this->assertSame([], $hits[0]->_source);
    }

    /**
     * @test
     */
    public function lazy_keeps_the_post_filter(): void
    {
        $connection = new class([HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"id":"pit-1"}')), HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"hits":{"hits":[{"_id":"1","_index":"orders","_score":1,"_source":{"status":"open"},"sort":[1,"1"]}]}}')), HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"hits":{"hits":[]}}')), HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"succeeded":true}'))]) implements ElasticsearchConnectionInterface
        {
            public array $bodies = [];

            public function __construct(private array $responses) {}

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponseContract
            {
                $this->bodies[] = (string) $request->getBody();

                return array_shift($this->responses);
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('no cluster');
            }

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };
        new RawQuery($connection, 'orders', [
            'query' => ['match_all' => (object) []],
            'post_filter' => ['term' => ['status' => 'paid']],
            'sort' => [['processed_at' => 'asc']],
        ]);

        $search = json_decode($connection->bodies[1], true);

        $this->assertSame(['term' => ['status' => 'paid']], $search['post_filter']);
    }

    /**
     * @test
     */
    public function pipeline_simulate_keeps_the_path_slash(): void
    {
        $connection = new class implements ElasticsearchConnectionInterface
        {
            public string $method = '';

            public string $uri = '';

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponseContract
            {
                $this->method = $request->getMethod();
                $this->uri = (string) $request->getUri();

                return HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"docs":[]}'));
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('no cluster');
            }

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };

        (new Pipeline($connection, 'auto_pipe'))->simulate([
            ['_source' => ['title' => 'Laravel Scout']],
        ]);

        $this->assertSame('POST', $connection->method);
        $this->assertSame('/_ingest/pipeline/auto_pipe/_simulate', $connection->uri);
    }

    /**
     * @test
     */
    public function an_empty_query_with_no_results_is_match_none(): void
    {
        $props = new NewProperties;
        $props->keyword('category');

        $blocked = $this->search($props)
            ->noResultsOnEmptySearch()
            ->queryString('')
            ->makeSearch()
            ->toRaw();
        $open = $this->search($props)
            ->queryString('')
            ->makeSearch()
            ->toRaw();

        $this->assertStringContainsString('match_none', json_encode($blocked['query']));
        $this->assertStringNotContainsString('"value":""', json_encode($blocked['query']));
        $this->assertStringContainsString('match_all', json_encode($open['query']));
        $this->assertStringNotContainsString('match_none', json_encode($open['query']));
        $this->assertStringNotContainsString('"value":""', json_encode($open['query']));
    }

    /**
     * @test
     */
    public function a_semantic_page_keeps_the_same_neighbors(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);
        $vector = array_fill(0, 128, 0.1);

        $second = $this->search($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('quantum computing', dimension: 128, vector: $vector)
            ->page(2, 10)
            ->makeSearch()
            ->toRaw();
        $first = $this->search($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('quantum computing', dimension: 128, vector: $vector)
            ->page(1, 10)
            ->makeSearch()
            ->toRaw();

        $this->assertSame(10, $second['from']);
        $this->assertSame(10, $second['size']);
        $this->assertSame(0, $first['from']);
        $this->assertGreaterThanOrEqual(20, $second['knn'][0]['k']);
        $this->assertEquals($first['knn'], $second['knn']);
    }

    /**
     * @test
     */
    public function lazy_keeps_inner_hit_matches(): void
    {
        $hit = [
            'hits' => [
                'hits' => [[
                    '_id' => '1',
                    '_index' => 'products',
                    '_score' => 1,
                    '_source' => ['title' => 'Phone'],
                    'inner_hits' => [
                        'reviews#1' => [
                            'hits' => [
                                'total' => ['value' => 1],
                                'hits' => [[
                                    '_score' => 2,
                                    '_source' => ['comment' => 'battery'],
                                    '_nested' => ['field' => 'reviews', 'offset' => 0],
                                ]],
                            ],
                        ],
                    ],
                ]],
            ],
        ];
        $connection = new class([HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"id":"pit-1"}')), HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode($hit))), HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"succeeded":true}'))]) extends ElasticsearchConnection
        {
            public function __construct(private array $responses) {}

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponseContract
            {
                return array_shift($this->responses);
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('The search must not call Elasticsearch.');
            }

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };

        $props = new NewProperties;
        $props->text('title');
        $props->nested('reviews', function (NewProperties $props): void {
            $props->text('comment');
        });

        $search = (new NewSearch($connection))
            ->index('products')
            ->properties($props)
            ->queryString('battery')
            ->innerHits('reviews.comment');

        $search->makeSearch();

        $formatted = $search->formatRespones($hit, $hit)->hits()[0]->_matches;
        $lazy = [];

        foreach ($search->lazy() as $found) {
            $lazy = $found->_matches;
            break;
        }

        $this->assertSame($formatted, $lazy);
        $this->assertSame(1, $lazy['reviews']['total']);
        $this->assertSame('battery', $lazy['reviews']['items'][0]['comment']);
    }

    /** @test */
    public function searches_and_facets_enter_a_nested_path_named_zero(): void
    {
        $properties = new NewProperties;
        $properties->nested('0', function (NewProperties $items): void {
            $items->keyword('sku');
        });

        $search = (new NewSearch($this->connection()))
            ->index('products')->properties($properties)->fields(['0.sku'])->queryString('abc')->makeSearch();
        $template = (new NewTemplate($this->connection()))
            ->id('products')->properties($properties)->fields(['0.sku'])->get();
        $facets = (new FacetParser($properties))->parse('0.sku')->toRaw();

        $this->assertStringContainsString('"path":"0"', json_encode($search->toRaw()['query']));
        $this->assertStringContainsString('"path":"0"', $template->source());
        $this->assertSame('0', $facets['0.sku']['nested']['path']);
    }

    /** @test */
    public function a_nested_filter_inside_path_zero_uses_the_full_child_path(): void
    {
        $properties = new NewProperties;
        $properties->nested('0', fn (NewProperties $parent): Nested => $parent->nested(
            'items', fn (NewProperties $items): Keyword => $items->keyword('sku')
        ));
        $raw = (new FilterParser($properties))->parse('0:{items:{sku:A}}')->toRaw();
        $outer = $raw['bool']['must'][0]['nested'];
        $child = $outer['query']['bool']['must'][0]['nested'];

        $this->assertSame('0', $outer['path']);
        $this->assertSame('0.items', $child['path']);
        $this->assertSame('A', $child['query']['bool']['must'][0]['term']['0.items.sku']['value']);
    }

    /** @test */
    public function facet_filter_groups_a_numeric_field_name(): void
    {
        $properties = new NewProperties;
        $properties->keyword('0');
        $this->assertSame('(0:red)', (new FacetParser($properties))->parseFilterString('0:red'));
    }

    /** @test */
    public function facet_filter_recognizes_operators_only_between_clauses(): void
    {
        $properties = new NewProperties;
        $properties->keyword('or');
        $properties->keyword('color');

        $parser = new FacetParser($properties);
        $this->assertSame('(or:red) AND (color:[blue,OR])', $parser->parseFilterString('or:red color:[blue,OR]'));
    }

    /** @test */
    public function facet_filter_keeps_spaces_in_lists_and_newlines_in_values(): void
    {
        $properties = new NewProperties;
        $properties->keyword('color');

        $parser = new FacetParser($properties);
        $this->assertSame('(color:[red, blue])', $parser->parseFilterString('color:[red, blue]'));
        $this->assertSame("(color:'a\nb')", $parser->parseFilterString("color:'a\nb'"));
    }

    public function test_a_serialized_zero_match_path_can_be_read_as_a_hit(): void
    {
        $matches = ['0' => ['total' => 1, 'items' => [['sku' => 'A']]]];
        $raw = (new Hit([], 'doc', 1.0, 'products', null, $matches))->toArray();
        $this->assertSame([['sku' => 'A']], Hit::fromRaw($raw)->matches('0'));
    }

    public function test_a_raw_reranked_hit_preserves_both_scores(): void
    {
        $hit = RerankedHit::fromRaw([
            '_source' => ['sku' => 'A'], '_id' => 'doc', '_index' => 'products',
            '_score' => 2.0, '_rerank_score' => 0.75,
        ]);

        $this->assertSame(2.0, $hit->_score);
        $this->assertSame(0.75, $hit->_rerank_score);
        $this->assertSame('A', $hit->_source['sku']);
    }

    public function test_reranking_a_hit_with_an_omitted_index_preserves_its_id(): void
    {
        $hit = new RerankedHit(new Hit([], 'doc', 1.0), 0.75);

        $this->assertSame('doc', $hit->_id);
        $this->assertFalse(isset($hit->_index));
    }

    public function test_geo_clauses_accept_coordinates_in_exponent_notation(): void
    {
        $props = new NewProperties;
        $props->geoPoint('pin');

        $latitude = 1e-6;
        $longitude = -2e-5;
        $sort = (new SortParser($props))->parse(sprintf('pin[%s,%s]:m:asc', $latitude, $longitude));
        $this->assertSame(['lat' => $latitude, 'lon' => $longitude], $sort[0]['_geo_distance']['pin']);

        $filter = (new FilterParser($props))->parse(sprintf('pin:1e2m[%s,%s]', $latitude, $longitude))->toRaw();
        $distance = $filter['bool']['must'][0]['geo_distance'];
        $this->assertSame('1e2m', $distance['distance']);
        $this->assertEquals(['lat' => $latitude, 'lon' => $longitude], $distance['pin']);
    }

    public function test_score_scripts_receive_exact_numeric_parameters(): void
    {
        $properties = new NewProperties;
        $properties->text('title');

        $search = $this->search($properties)->textScoreMultiplier(3000000000)->queryString('book');
        $body = json_encode($search->makeSearch()->toRaw(), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('"multiplier":3000000000', $body);
        $this->assertStringContainsString('_score * params.multiplier', $body);

        $properties->text('summary')->semantic(api: 'embeddings', dimensions: 128);
        $template = (new NewTemplate($this->connection()))->id('books')->properties($properties)
            ->semantic()->semanticThreshold(0.9999999999999999)->get()->source();
        $this->assertStringContainsString('"threshold":0.9999999999999999', $template);
        $this->assertStringContainsString('_score > params.threshold', $template);
    }

    public function test_template_minimum_score_keeps_its_precision(): void
    {
        $source = (new NewTemplate($this->connection()))->id('books')->properties(new NewProperties)
            ->minScore(1.0000000596046448)->get()->source();

        $this->assertStringContainsString('{{^minscore}}1.0000000596046448{{/minscore}}', $source);
    }

    public function test_large_hit_totals_count_the_last_page_exactly(): void
    {
        $page = $this->formatPage(0, 2, 9007199254740993);
        $this->assertSame(4503599627370497, $page['total_pages']);
    }

    public function test_field_sort_accepts_default_options(): void
    {
        $body = (new NewQuery($this->connection(), 'products'))->sort([['price' => []]])->toMultiSearch()[1];
        $this->assertSame('[{"price":{}}]', json_encode($body['sort']));
    }

    public function test_script_and_runtime_fields_encode_named_maps(): void
    {
        $script = ['script' => ['source' => 'return 1']];
        foreach (['script_fields' => $script, 'runtime_mappings' => ['type' => 'long']] as $key => $field) {
            $search = new Search($this->connection());
            $this->assertSame('{}', json_encode($search->addRaw($key, [])->toRaw()[$key]));
            $raw = new RawQuery($this->connection(), 'orders', [$key => []]);
            $this->assertSame('{}', json_encode($raw->toMultiSearch()[1][$key]));
            $this->assertSame(json_encode((object) [0 => $field]), json_encode($search->addRaw($key, [$field])->toRaw()[$key]));
        }
    }

    public function test_knn_without_a_lexical_query_only_matches_neighbors(): void
    {
        $search = (new Sigmie($this->connection()))->query('products')->knn(['field' => 'v', 'query_vector' => [1, 0], 'k' => 1]);
        $this->assertArrayNotHasKey('query', $search->toRaw());
        $this->assertArrayHasKey('query', $search->query(new MatchAll)->toRaw());
    }

    public function test_index_boost_preserves_a_numeric_index_name(): void
    {
        $query = new NewQuery($this->connection(), '0,orders');
        $query->matchAll()->addRaw('indices_boost', [['0' => 2]]);
        $this->assertSame('[{"0":2}]', json_encode($query->toMultiSearch()[1]['indices_boost']));
    }

    public function test_pit_multi_search_omits_the_index(): void
    {
        $body = ['pit' => ['id' => 'snapshot']];
        $query = new NewQuery($this->connection(), 'orders');
        $query->matchAll()->addRaw('pit', $body['pit']);
        foreach ([$query, new RawQuery($this->connection(), 'orders', $body)] as $builder) {
            $this->assertSame('{}', json_encode($builder->toMultiSearch()[0]));
        }
    }

    public function test_pit_search_uses_the_cluster_endpoint(): void
    {
        $connection = $this->createMock(ElasticsearchConnectionInterface::class);
        $connection->expects($this->once())->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponseContract {
            $this->assertSame('/_search', (string) $request->getUri());

            return $request->response(new PsrResponse(200, [], '{}'));
        });

        (new NewQuery($connection, 'orders'))->matchAll()->addRaw('pit', ['id' => 'snapshot'])->get();
    }

    public function test_lazy_query_uses_the_supplied_snapshot(): void
    {
        $connection = $this->createMock(ElasticsearchConnectionInterface::class);
        $connection->method('driver')->willReturn(new Elasticsearch);
        $connection->expects($this->once())->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponseContract {
            $this->assertSame('/_search', (string) $request->getUri());
            $this->assertSame(['id' => 'snapshot', 'keep_alive' => '3m'], $request->body()['pit']);

            return $request->response(new PsrResponse(200, [], '{"hits":{"hits":[]}}'));
        });
        $query = new NewQuery($connection, 'orders');
        $query->matchAll()->addRaw('pit', ['id' => 'snapshot', 'keep_alive' => '3m']);
        iterator_to_array($query->lazy());
    }

    private function formatPage(int $from, int $size, int $total): array
    {
        $formatter = (new SigmieSearchResponse(new Properties))
            ->context(new SearchContext(size: $size, from: $from))
            ->errors([])
            ->queryResponseRaw([
                'hits' => [
                    'total' => ['value' => $total],
                    'hits' => [],
                ],
            ]);

        return $formatter->format();
    }

    private function search(NewProperties $properties): NewSearch
    {
        return (new NewSearch($this->connection()))
            ->index('products')
            ->properties($properties);
    }

    private function connection(): ElasticsearchConnection
    {
        return new class extends ElasticsearchConnection
        {
            public function __construct() {}

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponseContract
            {
                throw new RuntimeException('The search must not call Elasticsearch.');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('The search must not call Elasticsearch.');
            }
        };
    }

    private function innerHit(NewProperties $properties, string $path, array $nested, array $source): array
    {
        $inner = new InnerHits;
        $inner->request([$path], 10);
        $inner->resolve($properties->get());

        $matches = $inner->matches([
            $inner->clause($path)['name'] => [
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [[
                        '_score' => 1,
                        '_source' => $source,
                        '_nested' => $nested,
                    ]],
                ],
            ],
        ]);

        return $matches[$path]['items'][0];
    }

    private function rank(array $sameVector, array $otherVector): string
    {
        $hit = fn (string $id, array $vector): array => [
            '_id' => $id,
            '_source' => ['_embeddings' => ['title' => $vector]],
        ];

        $ranked = (new MMR(1.0))->diversify(
            [$hit('other', $otherVector), $hit('same', $sameVector)],
            [$hit('seed', $sameVector)],
            'title',
            2,
        );

        return $ranked[0]['_id'];
    }

    /**
     * @test
     *
     * @dataProvider rawSorts
     */
    public function a_raw_scan_preserves_its_sort(string|array|stdClass $sort, array $expected): void
    {
        $connection = new class extends ElasticsearchConnection
        {
            public array $bodies = [];

            public function __construct() {}

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponseContract
            {
                $decoded = json_decode((string) $request->getBody(), true);

                if (is_array($decoded) && $decoded !== []) {
                    $this->bodies[] = $decoded;
                }

                if ($request->getMethod() === 'DELETE') {
                    return HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, [], '{"succeeded":true}'));
                }

                if (str_contains((string) $request->getUri(), '_pit')) {
                    return HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, [], '{"id":"pit-1"}'));
                }

                return HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, [], '{"hits":{"hits":[]}}'));
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('The search must not call Elasticsearch.');
            }
        };

        $query = new NewQuery($connection, 'orders');
        $query->matchAll()->addRaw('sort', $sort);
        foreach ([new RawQuery($connection, 'orders', ['sort' => $sort]), $query] as $builder) {
            $connection->bodies = [];
            iterator_to_array($builder->lazy());
            $withSort = array_values(array_filter($connection->bodies, fn (array $body): bool => isset($body['sort'])));
            $this->assertSame([...$expected, ['_shard_doc' => 'asc']], $withSort[0]['sort']);
        }
    }

    public static function rawSorts(): array
    {
        return [
            'string' => ['processed_at', ['processed_at']],
            'associative' => [
                ['processed_at' => ['order' => 'desc', 'missing' => '_last'], 'price' => 'asc'],
                [['processed_at' => ['order' => 'desc', 'missing' => '_last'], 'price' => 'asc']],
            ],
            'object' => [
                (object) ['processed_at' => 'desc'],
                [['processed_at' => 'desc']],
            ],
        ];
    }

    /**
     * @test
     */
    public function a_filter_value_that_looks_like_a_size_tag_is_returned(): void
    {
        $source = (new SearchTemplate($this->connection(), [
            'size' => '@size(20)@endsize',
            'query' => [
                'bool' => [
                    'filter' => '@filters({"term":{"category":"@size(1)"}})@endfilters',
                ],
            ],
        ], 'products'))->source();

        $this->assertStringContainsString('@size(1)', $source);
        $this->assertStringContainsString('{{#toJson}}size{{/toJson}}', $source);
    }

    /**
     * @test
     */
    public function a_query_string_default_keeps_a_literal_dollar(): void
    {
        $default = json_encode(['match' => ['title' => ['query' => 'a$1b']]]);
        $source = (new SearchTemplate($this->connection(), [
            'query' => '@query_string('.$default.')@endquery_string',
        ], 'products'))->source();

        $this->assertStringContainsString('{{#query_string}}'.$default.'{{/query_string}}', $source);
    }

    /**
     * @test
     */
    public function a_template_default_keeps_a_unicode_character(): void
    {
        $source = (new SearchTemplate($this->connection(), [
            'query' => [
                'bool' => [
                    'filter' => '@filters({"term":{"category":"café"}})@endfilters',
                ],
            ],
        ], 'products'))->source();

        $this->assertSame(1, preg_match('/\{\{\^filters\}\}(.*)\{\{\/filters\}\}/', $source, $matches));
        $this->assertSame(['term' => ['category' => 'café']], json_decode($matches[1], true));
    }

    /**
     * @test
     */
    public function a_rerank_hit_without_an_index_is_returned(): void
    {
        $api = new class implements RerankApi
        {
            public function rerank(array $newIndexes, string $query, ?int $topK = null): array
            {
                return [['index' => 0, 'score' => 0.5]];
            }
        };

        $out = (new NewRerank($api))
            ->query('battery')
            ->fields(['title'])
            ->rerank([[
                '_id' => 'aurora',
                '_score' => 1.0,
                '_source' => ['title' => 'Aurora Phone'],
            ]]);

        $this->assertInstanceOf(RerankedHit::class, $out[0]);
        $this->assertSame('aurora', $out[0]->_id);
        $this->assertSame('Aurora Phone', $out[0]->_source['title']);
        $this->assertSame(0.5, $out[0]->_rerank_score);
    }

    /**
     * @test
     */
    public function a_rerank_index_follows_the_payload_order(): void
    {
        $api = new class implements RerankApi
        {
            public function rerank(array $newIndexes, string $query, ?int $topK = null): array
            {
                return [['index' => 0, 'score' => 0.9]];
            }
        };

        $hits = [
            1 => new Hit(['title' => 'First'], 'first', 1.0, 'docs'),
            2 => new Hit(['title' => 'Second'], 'second', 1.0, 'docs'),
        ];

        $skipped = (new NewRerank($api))->query('')->fields(['title'])->rerank($hits);
        $out = (new NewRerank($api))
            ->query('q')
            ->fields(['title'])
            ->rerank($hits);

        $this->assertSame($hits, $skipped);
        $this->assertCount(1, $out);
        $this->assertInstanceOf(RerankedHit::class, $out[0]);
        $this->assertSame('first', $out[0]->_id);
        $this->assertSame(0.9, $out[0]->_rerank_score);
    }

    /**
     * @test
     */
    public function a_hit_source_named_zero_stays_in_the_context(): void
    {
        $hit = new Hit(['0' => 'zero'], '1', 1.0, 'docs');

        $this->assertSame('{"0":"zero"}', (new NewContextComposer)->compose([$hit]));
    }

    /**
     * @test
     */
    public function an_integer_hit_total_stays_the_count(): void
    {
        $query = new NewQuery($this->offlineMultiSearchConnection(), 'products');
        $body = static fn (int|array $total): array => [
            'responses' => [[
                'took' => 1,
                'hits' => [
                    'total' => $total,
                    'hits' => [['_id' => 'a', '_source' => ['title' => 'A']]],
                ],
            ]],
        ];

        $integer = (new SigmieMultiSearchResponse)
            ->multiSearchResponseRaw($body(5))
            ->searches([$query])
            ->format()[0]['total'];
        $object = (new SigmieMultiSearchResponse)
            ->multiSearchResponseRaw($body(['value' => 5, 'relation' => 'eq']))
            ->searches([$query])
            ->format()[0]['total'];

        $this->assertSame(5, $integer);
        $this->assertSame(5, $object);
    }

    /**
     * @test
     */
    public function a_replaced_multisearch_body_is_formatted(): void
    {
        $query = new NewQuery($this->offlineMultiSearchConnection(), 'products');
        $body = static fn (string $id): array => [
            'responses' => [[
                'took' => 1,
                'hits' => [
                    'total' => ['value' => 1, 'relation' => 'eq'],
                    'hits' => [['_id' => $id]],
                ],
            ]],
        ];

        $formatted = (new SigmieMultiSearchResponse)
            ->multiSearchResponseRaw($body('first'))
            ->searches([$query]);
        $formatted->format();
        $formatted->multiSearchResponseRaw($body('second'));

        $this->assertSame('second', $formatted->format()[0]['hits'][0]['_id']);
    }

    /**
     * @test
     */
    public function an_integer_search_total_is_the_count(): void
    {
        $response = (new SigmieSearchResponse(new Properties))
            ->queryResponseRaw([
                'took' => 3,
                'hits' => [
                    'total' => 5,
                    'hits' => [[
                        '_id' => '1',
                        '_index' => 'movies',
                        '_score' => 1.0,
                        '_source' => ['title' => 'A'],
                    ]],
                ],
            ])
            ->context(new SearchContext(size: 20, from: 0))
            ->errors([]);

        $this->assertSame(5, $response->total());
        $this->assertSame(5, $response->format()['total']);
        $this->assertSame(1, $response->format()['total_pages']);

        $object = (new SigmieSearchResponse(new Properties))
            ->queryResponseRaw([
                'took' => 3,
                'hits' => [
                    'total' => ['value' => 5, 'relation' => 'eq'],
                    'hits' => [],
                ],
            ])
            ->context(new SearchContext(size: 20, from: 0))
            ->errors([]);

        $this->assertSame(5, $object->total());
    }

    /**
     * @test
     */
    public function a_hit_without_an_index_or_a_score_is_returned(): void
    {
        $response = (new SigmieSearchResponse(new Properties))
            ->queryResponseRaw([
                'hits' => [
                    'total' => ['value' => 2, 'relation' => 'eq'],
                    'hits' => [
                        ['_id' => '1', '_score' => 1.5, '_source' => ['title' => 'A']],
                        ['_id' => '2', '_index' => 'movies', '_source' => ['title' => 'B']],
                    ],
                ],
            ])
            ->context(new SearchContext);

        $hits = $response->hits();

        $this->assertSame('1', $hits[0]->_id);
        $this->assertSame(1.5, $hits[0]->_score);
        $this->assertFalse(isset($hits[0]->_index));
        $this->assertSame('2', $hits[1]->_id);
        $this->assertNull($hits[1]->_score);
        $this->assertSame('movies', $hits[1]->_index);
    }

    /**
     * @test
     */
    public function a_facet_term_named_zero_stays_in_the_json(): void
    {
        $properties = new NewProperties;
        $properties->keyword('brand');

        $raw = [
            'took' => 1,
            'hits' => ['total' => ['value' => 4, 'relation' => 'eq'], 'hits' => []],
            'aggregations' => [
                'brand' => [
                    'doc_count' => 4,
                    'brand' => [
                        'buckets' => [
                            ['key' => '0', 'doc_count' => 4],
                        ],
                    ],
                ],
            ],
        ];

        $response = (new SigmieSearchResponse($properties->get()))
            ->queryResponseRaw($raw)
            ->facetsResponseRaw($raw)
            ->context(new SearchContext(facetFields: ['brand']))
            ->errors([]);

        $this->assertSame('{"0":4}', json_encode($response->json('facets.brand')));
        $this->assertSame('{"0":4}', json_encode($response->facet('brand')));
    }

    /**
     * @test
     */
    public function a_price_histogram_lower_bound_stays_in_the_json(): void
    {
        $properties = new NewProperties;
        $properties->price();

        $facet = function (array $buckets, float $min, float $max) use ($properties): array {
            $raw = [
                'aggregations' => [
                    'price' => [
                        'doc_count' => array_sum(array_column($buckets, 'doc_count')),
                        'price_histogram' => ['buckets' => $buckets],
                        'price_min' => ['value' => $min],
                        'price_max' => ['value' => $max],
                    ],
                ],
            ];

            return (new SigmieSearchResponse($properties->get()))
                ->facetsResponseRaw($raw)
                ->facet('price');
        };

        $sole = $facet([['key' => 0, 'doc_count' => 4]], 0, 10);
        $this->assertSame('{"0":4}', json_encode($sole['histogram']));

        $pair = $facet([
            ['key' => 0, 'doc_count' => 4],
            ['key' => 1, 'doc_count' => 2],
        ], 0, 1);
        $this->assertSame('{"0":4,"1":2}', json_encode($pair['histogram']));

        $spread = $facet([
            ['key' => 0, 'doc_count' => 4],
            ['key' => 50, 'doc_count' => 9],
        ], 0, 50);
        $this->assertSame([0 => 4, 50 => 9], $spread['histogram']);
        $this->assertSame('{"0":4,"50":9}', json_encode($spread['histogram']));
    }

    /**
     * @test
     */
    public function a_tags_facet_returns_value_counts(): void
    {
        $properties = new NewProperties;
        $properties->tags('label');
        $properties->nested('attributes', function (NewProperties $props): void {
            $props->tags('label');
        });

        $parser = new FacetParser($properties);
        $raw = $parser->parse('label attributes.label')->toRaw();

        $this->assertSame([], $parser->errors());
        $this->assertSame('label.keyword', $raw['label']['aggs']['label']['terms']['field']);
        $this->assertSame(
            'attributes.label.keyword',
            $raw['attributes.label']['aggs']['attributes.label']['aggs']['attributes.label']['terms']['field']
        );

        $buckets = [
            ['key' => 'summer', 'doc_count' => 4],
            ['key' => 'sale', 'doc_count' => 2],
        ];
        $response = (new SigmieSearchResponse($properties->get()))
            ->facetsResponseRaw([
                'aggregations' => [
                    'label' => [
                        'doc_count' => 6,
                        'label' => ['buckets' => $buckets],
                    ],
                    'attributes.label' => [
                        'attributes.label' => [
                            'doc_count' => 6,
                            'attributes.label' => ['buckets' => $buckets],
                        ],
                    ],
                ],
            ])
            ->context(new SearchContext(facetFields: ['label', 'attributes.label']))
            ->errors([]);

        $this->assertSame(['summer' => 4, 'sale' => 2], $response->facet('label'));
        $this->assertSame(['summer' => 4, 'sale' => 2], $response->facet('attributes.label'));
        $this->assertSame(
            ['summer' => 4, 'sale' => 2],
            $response->json('facets')['label']
        );
    }

    /**
     * @test
     */
    public function format_without_errors_returns_an_empty_list(): void
    {
        $response = (new SigmieSearchResponse(new Properties))
            ->queryResponseRaw([
                'took' => 1,
                'hits' => ['total' => ['value' => 1, 'relation' => 'eq'], 'hits' => []],
            ])
            ->context(new SearchContext(size: 20, from: 0));

        $this->assertSame([], $response->format()['errors']);
    }

    private function offlineMultiSearchConnection(): ElasticsearchConnection
    {
        $http = new class implements JSONClient
        {
            public function request(JSONRequest $request): JSONResponse
            {
                throw new RuntimeException('no http');
            }

            public function promise(JSONRequest $request): Promise
            {
                throw new RuntimeException('no http');
            }
        };

        return new ElasticsearchConnection($http, new Elasticsearch);
    }

    public function test_empty_price_range_keeps_null_extrema(): void
    {
        $props = new NewProperties;
        $props->price();

        $facet = (new SigmieSearchResponse($props->get()))
            ->facetsResponseRaw(['aggregations' => ['price' => [
                'price_min' => ['value' => null],
                'price_max' => ['value' => null],
                'price_histogram' => ['buckets' => []],
            ]]])
            ->facet('price');

        $this->assertNull($facet['min']);
        $this->assertNull($facet['max']);
    }

    public function test_empty_context_is_a_field_object(): void
    {
        $context = (new NewContextComposer)->fields(['actor'])->compose([new Hit(['film' => 'Up'], '1', null)]);

        $this->assertSame('{}', $context);
    }

    public function test_each_query_template_placeholder_keeps_its_default(): void
    {
        $source = (new SearchTemplate($this->connection(), [
            'first' => '@query_string({"term":{"title":"one"}})@endquery_string',
            'second' => '@query_string({"term":{"title":"two"}})@endquery_string',
        ], 'films'))->source();

        $this->assertSame(2, substr_count($source, '{{#query_string}}'));
        $this->assertStringContainsString('{{#query_string}}{"term":{"title":"two"}}{{/query_string}}', $source);
    }

    public function test_embedding_template_tags_match_literal_field_names(): void
    {
        $properties = new NewProperties;
        $properties->text('cost[net]')->semantic(api: 'embeddings', dimensions: 128);
        $source = (new NewTemplate($this->connection()))->id('films')->properties($properties)
            ->semantic()->get()->source();

        $this->assertStringContainsString('{{#toJson}}_embeddings_cost[net]{{/toJson}}', $source);
    }

    public function test_search_after_cursor_encodes_as_a_list(): void
    {
        $raw = $this->search(new NewProperties)->searchAfter([2 => 90, 5 => 'last'])->makeSearch()->toRaw();

        $this->assertSame('[90,"last"]', json_encode($raw['search_after']));
    }

    public function test_mmr_compares_vectors_with_matching_dimensions(): void
    {
        $hit = fn (string $id, array $vectors): array => [
            '_id' => $id,
            '_source' => ['_embeddings' => ['title' => $vectors]],
        ];
        $seed = $hit('seed', ['small' => [1, 0], 'large' => [0, 0, 1]]);
        $other = $hit('other', ['small' => [0, 1], 'large' => [0, 1, 0]]);
        $similar = $hit('similar', ['small' => [1, 0], 'large' => [0, 1, 0]]);

        $ranked = (new MMR(1.0))->diversify([$other, $similar], [$seed], 'title', 1);

        $this->assertSame('similar', $ranked[0]['_id']);
    }

    public function test_typo_tolerance_applies_only_to_the_named_field(): void
    {
        $props = new NewProperties;
        $props->title('1');
        $props->title('01');

        $search = $this->search($props)->typoTolerantAttributes(['01'])->queryString('moive');

        $this->assertStringNotContainsString('"fuzziness"', json_encode($search->fields(['1'])->makeSearch()->getDSL()));
        $this->assertStringContainsString('"fuzziness"', json_encode($search->fields(['01'])->makeSearch()->getDSL()));
    }

    public function test_boost_passes_the_field_name_as_script_data(): void
    {
        $props = new NewProperties;
        $props->boost("editor's rating");

        $query = $this->search($props)->queryString('')->makeSearch()->getDSL()['query'];
        $script = $query['function_score']['script_score']['script'];

        $this->assertSame("editor's rating", ((array) $script['params'])['field'] ?? null);
        $this->assertStringContainsString('doc[params.field]', $script['source']);
    }

    public function test_exact_vector_search_quotes_the_field_path(): void
    {
        $field = new ElasticsearchNestedVector("author's note");
        [$query] = $field->vectorQueries(array_fill(0, 384, 1.0), 10, new Boolean);
        $script = $query->toRaw()['nested']['query']['function_score']['script_score']['script'];

        $this->assertStringContainsString("_embeddings.author\\'s note.vector", $script['source']);
    }

    public function test_filtered_exclusions_stay_a_list_in_inner_hits(): void
    {
        $props = new NewProperties;
        $props->nested('reviews', fn (NewProperties $item): Text => $item->text('comment'));

        $body = $this->search($props)->queryString('book', fields: ['reviews.comment'])
            ->innerHits('reviews', 2)->except([1 => 'reviews.author'])->makeSearch()->toRaw();

        $this->assertStringContainsString('"excludes":["reviews.author"]', json_encode($body['query']));
    }

    /** @test */
    public function clearing_highlighting_removes_the_request_clause(): void
    {
        $properties = new NewProperties;
        $properties->text('title');

        $builder = $this->search($properties)->highlighting(['title'], '<b>', '</b>');

        $this->assertSame(['<b>'], $builder->makeSearch()->toRaw()['highlight']['fields']['title']['pre_tags']);
        $this->assertArrayNotHasKey('highlight', $builder->highlighting([], '<b>', '</b>')->makeSearch()->toRaw());
    }

    /** @test */
    public function autocomplete_uses_the_latest_prefix_on_a_reused_search(): void
    {
        $properties = new NewProperties;
        $properties->completion('title');

        $builder = $this->search($properties)->autocompletePrefix('st');

        $this->assertSame('st', $builder->makeSearch()->toRaw()['suggest']['autocompletion']['prefix']);
        $this->assertSame('star', $builder->autocompletePrefix('star')->makeSearch()->toRaw()['suggest']['autocompletion']['prefix']);
        $this->assertArrayNotHasKey('suggest', $builder->autocompletePrefix('')->makeSearch()->toRaw());
    }

    /** @test */
    public function a_built_nested_search_keeps_its_inner_hits_configuration(): void
    {
        $properties = new NewProperties;
        $properties->nested('reviews', fn (NewProperties $fields): Text => $fields->text('comment'));

        $builder = $this->search($properties)
            ->queryString('battery')
            ->innerHits('reviews', size: 2);
        $search = $builder->makeSearch();
        $first = json_encode($search->toRaw());

        $updated = $builder->except(['reviews.comment'])->innerHits('reviews', size: 4)->makeSearch()->toRaw();

        $this->assertStringContainsString('"size":4,"_source"', json_encode($updated));
        $this->assertSame($first, json_encode($search->toRaw()));
    }

    /** @test */
    public function identical_nested_searches_keep_stable_unique_inner_hit_names(): void
    {
        $properties = new NewProperties;
        $properties->nested('reviews', function (NewProperties $fields): void {
            $fields->text('title');
            $fields->text('comment');
        });
        $builder = $this->search($properties)->queryString('battery')->innerHits('reviews');
        $first = $builder->makeSearch();
        $second = $builder->makeSearch();
        $json = json_encode($first->toRaw());

        preg_match_all('/"name":"(reviews#\d+)"/', $json, $names);
        $this->assertCount(2, $names[1]);
        $this->assertCount(2, array_unique($names[1]));
        $this->assertSame($json, json_encode($second->toRaw()));
    }

    /** @test */
    public function offset_pagination_clears_a_previous_search_after_cursor(): void
    {
        $builder = $this->search(new NewProperties)->searchAfter([42]);
        $this->assertSame([42], $builder->makeSearch()->toRaw()['search_after']);

        $offset = $builder->page(2, 10)->makeSearch()->toRaw();
        $this->assertSame(10, $offset['from']);
        $this->assertArrayNotHasKey('search_after', $offset);

        $emptyCursor = $builder->searchAfter([42])->searchAfter([])->makeSearch()->toRaw();
        $this->assertSame(0, $emptyCursor['from']);
        $this->assertArrayNotHasKey('search_after', $emptyCursor);
    }

    /** @test */
    public function lazy_search_keeps_its_inner_hits_limit_when_the_builder_is_reused(): void
    {
        $item = static fn (int $offset): array => [
            '_nested' => ['field' => 'reviews', 'offset' => $offset],
            '_source' => ['comment' => 'battery'],
        ];
        $hit = [
            '_id' => 'first',
            'inner_hits' => ['reviews#1' => ['hits' => ['total' => ['value' => 2], 'hits' => [$item(0), $item(1)]]]],
        ];
        $connection = $this->respondingConnection(
            ['id' => 'pit-audit-reuse'],
            ['hits' => ['hits' => [$hit, [...$hit, '_id' => 'second']]]],
            ['succeeded' => true],
        );
        $properties = new NewProperties;
        $properties->nested('reviews', fn (NewProperties $fields): Text => $fields->text('comment'));

        $builder = (new NewSearch($connection))->index('products')->properties($properties)
            ->queryString('battery')->innerHits('reviews', size: 1);
        $hits = $builder->lazy();
        $this->assertCount(1, $hits->current()->matches('reviews'));

        $builder->innerHits('reviews', size: 2)->makeSearch();
        $hits->next();

        $this->assertSame('second', $hits->current()->_id);
        $this->assertCount(1, $hits->current()->matches('reviews'));
    }

    /** @test */
    public function reused_multi_search_reports_the_latest_http_failure(): void
    {
        $connection = $this->createMock(ElasticsearchConnection::class);
        $connection->method('__invoke')->willReturnCallback(function (ElasticsearchRequest $request): ElasticsearchResponseContract {
            static $calls = 0;

            if (++$calls === 2) {
                throw new ElasticsearchException(['error' => 'unavailable'], 503);
            }

            return $request->response(new PsrResponse(200, [], '{"responses":[{}]}'));
        });
        $multi = new NewMultiSearch($connection);
        $multi->raw('products', ['query' => ['match_all' => (object) []]]);
        $multi->get();
        $this->assertSame(200, $multi->responseCode());

        try {
            $multi->get();
            $this->fail('Expected the failed request to throw.');
        } catch (ElasticsearchException $elasticsearchException) {
            $this->assertSame(503, $elasticsearchException->getCode());
        }

        $this->assertSame(503, $multi->responseCode());
    }

    public function test_autocomplete_finds_a_completion_field_inside_an_object(): void
    {
        $props = new NewProperties;
        $props->object('book', function (NewProperties $book): void {
            $book->completion('title');
        });
        $body = $this->search($props)->autocompletePrefix('sta')->makeSearch()->toRaw();

        $this->assertSame('book.title', $body['suggest']['autocompletion']['completion']['field'] ?? null);
    }

    public function test_disabled_autocomplete_omits_suggestions_for_an_explicit_prefix(): void
    {
        $properties = new NewProperties;
        $properties->completion('title');

        $body = $this->search($properties)->autocomplete(false)->autocompletePrefix('sta')->makeSearch()->toRaw();

        $this->assertArrayNotHasKey('suggest', $body);
    }

    public function test_mmr_compares_vectors_within_their_embedding_space(): void
    {
        $hit = fn (string $id, array $a, array $b): array => [
            '_id' => $id,
            '_source' => ['_embeddings' => ['title' => ['model_a' => $a, 'model_b' => $b]]],
        ];
        $seed = $hit('seed', [1, 0], [0, 1]);
        $ranked = (new MMR(1))->diversify([
            $hit('swapped', [0, 1], [1, 0]),
            $hit('same', [1, 0], [0, 1]),
        ], [$seed], 'title', 1);

        $this->assertSame('same', $ranked[0]['_id']);
    }

    public function test_literal_dotted_rerank_source_in_list(): void
    {
        $payload = NewRerank::documentPayloads([['_source' => ['items' => [
            ['part.name' => 'Ada'], ['part.name' => 'Grace'],
        ]]]], ['items.part.name']);
        $this->assertSame(['items.part.name' => ['Ada', 'Grace']], Yaml::parse($payload[0]));
    }

    public function test_rerank_selects_dotted_object_keys_inside_lists(): void
    {
        $payload = NewRerank::documentPayloads([['_source' => ['items' => [
            ['part.author' => ['name' => 'Ada']],
            ['part.author' => ['name' => 'Grace']],
        ]]]], ['items.part.author.name']);

        $this->assertSame(['items.part.author.name' => ['Ada', 'Grace']], Yaml::parse($payload[0]));
    }
}
