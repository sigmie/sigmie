<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use RuntimeException;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse as ElasticsearchResponseContract;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\Drivers\Opensearch;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Document\Document;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\ElasticsearchNestedVector;
use Sigmie\Mappings\Types\FlatObject;
use Sigmie\Mappings\Types\Image;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Search\NewSearch;
use Sigmie\Search\VectorPool;
use Sigmie\Semantic\DocumentProcessor;
use Sigmie\Semantic\Providers\Noop;
use Sigmie\Semantic\Reranker;
use Sigmie\Testing\TestCase;

class SemanticTest extends TestCase
{
    public function test_vector_page_collapses_groups_before_limiting_hits(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 2);
        $props->keyword('brand');
        $field = $props->get()->get('title')->vectorFields()->first()->name;
        $index = uniqid('vector_groups_');
        $this->sigmie->newIndex($index)->properties($props)->create();
        $documents = [];
        foreach ([['a', [1, 0]], ['a', [1, 0]], ['b', [0, 1]]] as [$brand, $vector]) {
            $documents[] = new Document(['title' => $brand, 'brand' => $brand, '_embeddings' => ['title' => [$field => $vector]]]);
        }

        $this->sigmie->collect($index, refresh: true)->properties($props)->merge($documents);

        $hits = $this->sigmie->newSearch($index)->properties($props)->semantic()->disableKeywordSearch()
            ->queryString('', vector: [1, 0])->uniqueBy('brand')->size(2)->get()->hits();

        $this->assertCount(2, $hits);
    }

    public function test_numeric_boost_field_uses_the_value_inside_the_record(): void
    {
        $props = new NewProperties;
        $props->nested('items', function (NewProperties $row): void {
            $row->number('0');
            $row->text('title')->semantic(api: 'e', dimensions: 3)->normalizeVector(false)->boostedBy('items.0');
        });
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $this->recordingEmbeddings()]);
        $doc = $processor->populateEmbeddings(new Document(['items' => [['0' => 2, 'title' => 'guide']]]));

        $this->assertSame([2.0, 4.0, 6.0], $this->firstVector($doc['_embeddings']));
    }

    public function test_unnamed_vector_mapping_uses_the_supplied_default_pool(): void
    {
        $props = new NewProperties;
        $props->text('title')->newSemantic(fn ($field) => $field->accuracy(1, 2));
        $pool = (new VectorPool($this->createStub(EmbeddingsApi::class)))->setPool(['guide' => [2 => [1, 0]]]);
        $body = $this->semanticBuilder($props)->semantic()->queryString('guide')
            ->setVectorPool($pool)->makeSearch()->toRaw();

        $this->assertSame([1, 0], $body['knn'][0]['query_vector'] ?? null);
    }

    public function test_object_patch_clears_a_nested_combo_embedding(): void
    {
        $props = new NewProperties;
        $props->object('author', function (NewProperties $fields): void {
            $fields->text('name');
            $fields->combo('summary', ['name'])->semantic(api: 'e');
        });
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $this->recordingEmbeddings()]);
        $doc = new Document(['author' => ['name' => null]]);
        $processor->populateEmbeddings($processor->populateComboFields($doc, partial: true));

        $this->assertSame(['name' => null, 'summary' => null], $doc['author']);
        $this->assertSame(['author' => ['summary' => null]], $doc['_embeddings']);
    }

    public function test_vector_page_scores_every_selected_field_before_limiting_hits(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 2);
        $props->text('summary')->semantic(api: 'e', dimensions: 2);
        $field = $props->get()->get('title')->vectorFields()->first()->name;
        $index = uniqid('combined_vectors_');
        $this->sigmie->newIndex($index)->properties($props)->create();
        $documents = [];
        foreach (['title' => [[1, 0], [-1, 0]], 'summary' => [[-1, 0], [1, 0]], 'both' => [[0.8, 0.6], [0.8, 0.6]]] as $id => [$title, $summary]) {
            $documents[] = new Document(['title' => $id, 'summary' => $id, '_embeddings' => [
                'title' => [$field => $title], 'summary' => [$field => $summary],
            ]], $id);
        }

        $this->sigmie->collect($index, refresh: true)->properties($props)->merge($documents);

        $hits = $this->sigmie->newSearch($index)->properties($props)->semantic()->disableKeywordSearch()
            ->queryString('', vector: [1, 0])->size(1)->get()->hits();

        $this->assertSame('both', $hits[0]->_id);
    }

    public function test_exact_vector_search_applies_the_semantic_score_multiplier(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 7, dimensions: 2);
        $vector = $props->get()->get('title')->vectorFields()->first()->name;
        $index = uniqid('exact_vector_weight_');
        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document(['title' => 'guide', '_embeddings' => ['title' => [$vector => [['vector' => [1, 0]]]]]], 'guide'),
        ]);

        $hit = $this->sigmie->newSearch($index)->properties($props)->semantic()->disableKeywordSearch()
            ->queryString('', vector: [1, 0])->semanticScoreMultiplier(3)->get()->hits()[0];

        $this->assertEqualsWithDelta(6.0, $hit->_score, 0.00001);
    }

    public function test_exact_cosine_search_accepts_opposite_vectors(): void
    {
        $this->forOpensearch(fn () => $this->markTestSkipped('Elasticsearch exact vector score'));
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 7, dimensions: 2);
        $vector = $props->get()->get('title')->vectorFields()->first()->name;
        $index = uniqid('exact_cosine_opposite_');
        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document(['title' => 'guide', '_embeddings' => ['title' => [$vector => [['vector' => [4, 1]]]]]], 'guide'),
        ]);

        $hits = $this->sigmie->newSearch($index)->properties($props)->semantic()->disableKeywordSearch()
            ->queryString('', vector: [-4, -1])->get()->hits();

        $this->assertSame(['guide'], array_column($hits, '_id'));
        $this->assertEqualsWithDelta(0.0, $hits[0]->_score, 0.00001);
    }

    public function test_single_vector_search_reports_the_same_total_on_each_page(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 2);
        $vector = $props->get()->get('title')->vectorFields()->first()->name;
        $index = uniqid('single_vector_total_');
        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document(['title' => 'near', '_embeddings' => ['title' => [$vector => [1, 0]]]], 'near'),
            new Document(['title' => 'far', '_embeddings' => ['title' => [$vector => [0, 1]]]], 'far'),
        ]);

        $search = $this->sigmie->newSearch($index)->properties($props)->semantic()->disableKeywordSearch()
            ->queryString('', vector: [1, 0]);
        $first = $search->page(1, 1)->get();
        $second = $search->page(2, 1)->get();

        $this->assertSame(['near'], array_column($first->hits(), '_id'));
        $this->assertSame(['far'], array_column($second->hits(), '_id'));
        $this->assertSame([2, 2], [$first->total(), $second->total()]);
    }

    public function test_vector_facet_counts_ignore_selection_on_their_own_field(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 2);
        $props->keyword('brand');
        $vector = $props->get()->get('title')->vectorFields()->first()->name;
        $index = uniqid('vector_facets_');
        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document(['title' => 'first', 'brand' => 'dell', '_embeddings' => ['title' => [$vector => [1, 0]]]], 'dell'),
            new Document(['title' => 'second', 'brand' => 'hp', '_embeddings' => ['title' => [$vector => [0, 1]]]], 'hp'),
        ]);
        $search = $this->sigmie->newSearch($index)->properties($props)->semantic()->disableKeywordSearch()
            ->queryString('', vector: [1, 0])->facets('brand', "brand:'dell'");

        $response = $search->get();
        $this->assertSame(['dell'], array_column($response->hits(), '_id'));
        $this->assertSame(['dell' => 1, 'hp' => 1], $response->facet('brand'));

        $multi = $this->sigmie->newMultiSearch();
        $multi->add($search);
        $multi->newSearch($index)->properties($props)->filters("brand:'hp'");
        [$selected, $next] = $multi->get();
        $this->assertSame(['dell' => 1, 'hp' => 1], $selected->facet('brand'));
        $this->assertSame(['hp'], array_column($next->hits(), '_id'));
    }

    public function test_vector_page_ranks_document_boosts_before_limiting_hits(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 2);
        $props->boost('priority');
        $vectorName = $props->get()->get('title')->vectorFields()->first()->name;
        $index = uniqid('weighted_vector_');
        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document(['title' => 'first', 'priority' => 1, '_embeddings' => ['title' => [$vectorName => [1, 0]]]], 'first'),
            new Document(['title' => 'second', 'priority' => 5, '_embeddings' => ['title' => [$vectorName => [0, 1]]]], 'second'),
        ]);

        $hits = $this->sigmie->newSearch($index)->properties($props)->semantic()->disableKeywordSearch()
            ->queryString('', vector: [1, 0])->size(1)->get()->hits();

        $this->assertSame('second', $hits[0]->_id);
    }

    public function test_unusable_vector_only_query_matches_nothing(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 128);
        $query = $this->semanticBuilder($props)->semantic()
            ->queryString('', vector: [1, 0])->makeSearch()->toRaw()['query'];

        $this->assertStringContainsString('match_none', json_encode($query));
    }

    public function test_relative_semantic_fields_resolve_on_named_properties(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 2);
        $body = $this->semanticBuilder($props)->properties($props->get(name: 'product'))->semantic()
            ->queryString('book', fields: ['title'], vector: [1, 0])->makeSearch()->toRaw();

        $this->assertStringStartsWith('_embeddings.product.title.', $body['knn'][0]['field'] ?? '');
    }

    protected function setUp(): void
    {
        if ($this->runsWithoutCluster()) {
            return;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->runsWithoutCluster()) {
            return;
        }

        parent::tearDown();
    }

    private function runsWithoutCluster(): bool
    {
        return in_array($this->getName(false), [
            'test_replacing_nested_records_clears_absent_embeddings',
            'test_stored_embedding_object_skips_provider',
            'test_combo_reads_dotted_fields_through_json_objects',
            'test_blank_semantic_values_skip_embedding',
            'test_combo_fields_follow_source_objects',
            'test_unusable_vector_only_query_matches_nothing',
            'test_unnamed_vector_mapping_uses_the_supplied_default_pool',
            'test_numeric_boost_field_uses_the_value_inside_the_record',
            'test_object_patch_clears_a_nested_combo_embedding',
            'test_relative_semantic_fields_resolve_on_named_properties',
            'clip_receives_each_gallery_image_at_accuracy_one',
            'null_items_in_a_text_list_are_ignored_during_validation',
            'a_list_of_ranges_is_validated_per_range',
            'semantic_text_lists_are_flattened_before_embedding',
            'an_empty_combo_source_clears_its_previous_value',
            'noop_batch_embed_passes_the_text_and_the_field_type',
            'top_level_semantic_zero_is_embedded',
            'nested_dates_are_formatted_inside_each_item',
            'a_double_nested_semantic_field_embeds_every_value',
            'a_nested_boost_scales_the_item_vector',
            'an_object_list_joins_into_one_embedding',
            'a_combo_field_under_object_and_nested_is_written',
            'a_combo_field_reads_through_a_list',
            'an_object_list_of_nested_items_is_embedded',
            'a_date_range_bound_is_formatted',
            'a_one_element_boost_list_scales_the_vector',
            'a_script_score_for_dot_product_stays_non_negative',
            'a_knn_num_candidates_stays_within_the_limit',
            'an_empty_field_list_skips_vector_queries',
            'a_filter_before_properties_stays_on_the_query',
            'a_sort_and_facets_before_properties_stay_on_the_query',
            'a_caller_vector_is_used_without_an_api_or_a_dimension',
            'an_immutable_datetime_is_formatted',
            'noop_satisfies_the_provider_contract',
            'test_normalized_vectors_keep_their_direction_with_tiny_boosts',
            'test_combo_reads_objects_from_flattened_fields',
        ], true);
    }

    public function test_combo_reads_objects_from_flattened_fields(): void
    {
        $props = new NewProperties;
        $props->type(new FlatObject('metadata'));
        $props->combo('summary', ['metadata']);

        $processor = new DocumentProcessor($props->get());
        $document = new Document(['metadata' => (object) [
            'label' => 'report',
            'authors' => [(object) ['name' => 'Ada'], (object) ['name' => 'Grace']],
        ]]);

        $processor->populateComboFields($processor->validateFields($document));

        $this->assertSame('report Ada Grace', $document['summary']);
    }

    public function test_normalized_vectors_keep_their_direction_with_tiny_boosts(): void
    {
        $props = new NewProperties;
        $props->number('weight')->double();
        $props->text('title')->semantic(api: 'e', accuracy: 1, dimensions: 128)->boostedBy('weight');
        $props->text('exact')->semantic(api: 'e', accuracy: 7, dimensions: 128)->boostedBy('weight');
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $this->recordingEmbeddings()]);
        $source = ['title' => 'hello', 'exact' => 'hello'];
        $regular = $processor->populateEmbeddings(new Document($source + ['weight' => 1.0]));
        $tiny = $processor->populateEmbeddings(new Document($source + ['weight' => PHP_FLOAT_MIN * PHP_FLOAT_EPSILON]));

        $this->assertSame($regular['_embeddings'], $tiny['_embeddings']);
    }

    public function test_blank_semantic_values_skip_embedding(): void
    {
        $props = new NewProperties;
        $props->text('tags')->semantic(api: 'e', dimensions: 128, accuracy: 1);
        $api = $this->recordingEmbeddings();
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $api]);
        [$blank, $mixed] = $processor->populateEmbeddingsBatch([
            new Document(['tags' => '']),
            new Document(['tags' => [' ', '0', 'kept']]),
        ]);

        $this->assertSame(['0 kept'], $api->texts);
        $this->assertSame(['tags' => null], $blank->_source['_embeddings']);
        $this->assertNotEmpty($mixed->_source['_embeddings']['tags']);
    }

    public function test_combo_fields_follow_source_objects(): void
    {
        $props = new NewProperties;
        $props->object('meta', function (NewProperties $inner): void {
            $inner->text('0');
            $inner->combo('full', ['0']);
        });
        $processor = new DocumentProcessor($props->get());
        $document = $processor->populateComboFields(new Document(['meta' => [
            (object) ['0' => 'first'], ['0' => 'second'],
        ]]));
        $this->assertSame('first', $document->_source['meta'][0]->full ?? null);
        $this->assertSame('second', $document->_source['meta'][1]['full'] ?? null);
    }

    public function test_replacing_nested_records_clears_absent_embeddings(): void
    {
        $props = new NewProperties;
        $props->nested('comments', function (NewProperties $inner): void {
            $inner->text('body')->semantic(api: 'e', dimensions: 128);
        });
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $this->recordingEmbeddings()]);
        foreach ([[], null, [['author' => 'Ada']]] as $replacement) {
            $doc = $processor->populateEmbeddings(new Document(['comments' => $replacement]));
            $this->assertSame(['comments' => ['body' => null]], $doc->_source['_embeddings'] ?? []);
        }

        $patch = $processor->populateEmbeddings(new Document(['comments' => ['author' => 'Ada']]));
        $this->assertArrayNotHasKey('_embeddings', $patch->_source);
    }

    public function test_stored_embedding_object_skips_provider(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 128, accuracy: 1);
        $schema = $props->get();
        $name = $schema->get('title')->vectorFields()->first()->name;
        $stored = array_fill(0, 128, 0.25);
        $api = $this->recordingEmbeddings();
        $document = new Document(['title' => 'book', '_embeddings' => (object) [
            'title' => (object) [$name => $stored],
        ]]);
        (new DocumentProcessor($schema))->apis(['e' => $api])->populateEmbeddings($document);

        $this->assertSame([], $api->texts);
        $this->assertSame($stored, $document->get('_embeddings.title.'.$name));
    }

    public function test_combo_reads_dotted_fields_through_json_objects(): void
    {
        $props = new NewProperties;
        $props->object('authors', fn (NewProperties $inner): Text => $inner->text('name'));
        $props->combo('full', ['authors.name']);

        $document = new Document(['authors' => [(object) ['name' => 'Ada'], (object) ['name' => 'Grace']]]);
        (new DocumentProcessor($props->get()))->populateComboFields($document);

        $this->assertSame('Ada Grace', $document->get('full'));
    }

    /** @test */
    public function clip_receives_each_gallery_image_at_accuracy_one(): void
    {
        $props = new NewProperties;
        $props->image('gallery')->semantic(api: 'e', accuracy: 1, dimensions: 128);
        $api = $this->recordingEmbeddings();
        $urls = ['https://example.com/a.png', 'https://example.com/b.png'];
        (new DocumentProcessor($props->get()))->apis(['e' => $api])->populateEmbeddings(new Document(['gallery' => $urls]));
        $this->assertSame($urls, $api->texts);
    }

    /** @test */
    public function null_items_in_a_text_list_are_ignored_during_validation(): void
    {
        $props = new NewProperties;
        $props->text('tags');

        $document = new Document(['tags' => ['book', null]]);
        $this->assertSame($document, (new DocumentProcessor($props->get()))->validateFields($document));
    }

    /** @test */
    public function a_list_of_ranges_is_validated_per_range(): void
    {
        $props = new NewProperties;
        $props->range('stock')->integer();
        $document = new Document(['stock' => [['gte' => 1, 'lt' => 3], ['gte' => 10]]]);
        $this->assertSame($document, (new DocumentProcessor($props->get()))->validateFields($document));
    }

    /** @test */
    public function semantic_text_lists_are_flattened_before_embedding(): void
    {
        $props = new NewProperties;
        $props->text('tags')->semantic(api: 'e', dimensions: 128, accuracy: 1);
        $api = $this->recordingEmbeddings();
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $api]);
        $processor->populateEmbeddings(new Document(['tags' => ['A', ['B', 'C']]]));
        $this->assertSame(['A B C'], $api->texts);
    }

    public function test_partial_collection_merge_preserves_omitted_semantic_fields(): void
    {
        $name = uniqid('semantic_merge_');
        $properties = new NewProperties;
        $properties->text('title')->semantic(api: 'test-embeddings', dimensions: 128, accuracy: 1);
        $properties->text('summary')->semantic(api: 'test-embeddings', dimensions: 128, accuracy: 1);
        $properties->keyword('status');
        $index = $this->sigmie->newIndex($name)->properties($properties)->create();
        try {
            $collection = $this->sigmie->collect($name, refresh: true)->properties($properties);
            $collection->add(new Document(['title' => 'Title', 'summary' => 'Summary', 'status' => 'draft'], '1'));
            $before = $collection->get('1')->_source['_embeddings'];

            $collection->merge([new Document(['status' => 'published'], '1')]);

            $stored = $collection->get('1')->_source;
            $this->assertSame('published', $stored['status']);
            $this->assertSame($before, $stored['_embeddings']);

            $collection->merge([new Document(['title' => null], '1')]);
            $stored = $collection->get('1')->_source;
            $this->assertNull($stored['_embeddings']['title']);
            $this->assertSame($before['summary'], $stored['_embeddings']['summary']);
        } finally {
            $index->delete();
        }
    }

    /** @test */
    public function an_empty_combo_source_clears_its_previous_value(): void
    {
        $props = new NewProperties;
        $props->text('name');
        $props->combo('full', ['name']);

        $processor = new DocumentProcessor($props->get());
        $document = $processor->populateComboFields(new Document(['name' => 'Ada']));
        $this->assertSame('Ada', $document->_source['full']);
        $document['name'] = '';
        $processor->populateComboFields($document);
        $this->assertArrayNotHasKey('full', $document->_source);
    }

    /**
     * @test
     */
    public function brute_force_nested_semantic_fields_filters(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->title('title');
        $blueprint->nested('charachter', function (NewProperties $blueprint): void {
            $blueprint->nested('details', function (NewProperties $blueprint): void {
                $blueprint->nested('meta', function (NewProperties $blueprint): void {
                    $blueprint->nested('extra', function (NewProperties $blueprint): void {
                        $blueprint->nested('deep', function (NewProperties $blueprint): void {
                            $blueprint->title('deepnote')->semantic(accuracy: 7, dimensions: 384, api: 'test-embeddings');
                        });
                    });
                });
            });
        });

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                $this->nestedCharacterDocument('King', true),
                $this->nestedCharacterDocument('Queen', true),
                $this->nestedCharacterDocument('Princess', false),
                $this->nestedCharacterDocument('Lady', false),
            ]);

        $search = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->disableKeywordSearch()
            ->filters('active:true')
            ->queryString('woman')
            ->size(2);

        /** @var SigmieSearchResponse $res */
        $res = $search->get();

        // Verify results
        $hits = $res->hits();
        $totalHits = $res->total();

        // Should only return 2 results (King and Queen), not 1
        $this->assertEquals(2, $totalHits, '');

        // Verify all returned documents have active:true
        foreach ($hits as $hit) {
            $this->assertTrue(
                $hit->_source['active'],
                'All returned documents must have active:true'
            );
        }

        // Verify we get Queen as top result (most relevant to "woman" and "lady")
        $topHit = $res->hits()[0]->_source;
        $this->assertEquals(
            'Queen',
            $topHit['charachter'][0]['details'][0]['meta'][0]['extra'][0]['deep'][0]['deepnote'][0] ?? null,
            'Queen should be the top result for "woman" and "lady" query'
        );

        $this->assertEquals(
            'King',
            $res->hits()[1]->_source['title'] ?? null,
            "King should be the second because it's active compared to lady"
        );
    }

    /**
     * @test
     */
    public function document_processor_helper_paths_are_backed_by_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->title('title')->semantic(accuracy: 1, dimensions: 128, api: 'test-embeddings');
        $blueprint->range('age_range')->integer();
        $blueprint->nested('comments', function (NewProperties $props): void {
            $props->title('body')->semantic(accuracy: 1, dimensions: 128, api: 'test-embeddings');
        });

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->populateEmbeddings(false)
            ->add(new Document([
                'title' => 'Processor coverage',
                'age_range' => ['gte' => 18, 'lte' => 65],
                'comments' => [[
                    'body' => 'Single nested comment',
                ]],
            ], _id: 'matching'));

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('Processor')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $properties = $blueprint->get();
        $processor = new class($properties) extends DocumentProcessor
        {
            public function reuseCoverage(Text|Image $field, Document $document): ?array
            {
                return $this->reuseExistingEmbeddings($field, $document);
            }

            public function validationErrorsCoverage(string $fieldPath, mixed $value, Type $field): array
            {
                $errors = [];

                $this->validateFieldValue($fieldPath, $value, $field, $errors);

                return $errors;
            }
        };

        $title = $properties->get('title');
        $vectorField = array_values($title->vectorFields()->toArray())[0];

        $this->assertNull($processor->reuseCoverage($title, new Document([
            '_embeddings' => [
                'title' => [
                    $vectorField->name => [],
                ],
            ],
        ])));

        $processor->apis(['test-embeddings' => $this->embeddingApi])
            ->populateEmbeddings($hits[0]);
        $this->embeddingApi->assertBatchEmbedWasCalledWith('Single nested comment');

        $this->assertNotEmpty($processor->validationErrorsCoverage('age_range', 'invalid', $properties->get('age_range')));
    }

    /**
     * @test
     */
    public function nested_semantic_fields(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->title('title');
        $blueprint->nested('charachter', function (NewProperties $blueprint): void {
            $blueprint->nested('details', function (NewProperties $blueprint): void {
                $blueprint->nested('meta', function (NewProperties $blueprint): void {
                    $blueprint->nested('extra', function (NewProperties $blueprint): void {
                        $blueprint->nested('deep', function (NewProperties $blueprint): void {
                            $blueprint->title('deepnote')->semantic(accuracy: 3, dimensions: 384, api: 'test-embeddings');
                        });
                    });
                });
            });
        });

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                $this->nestedCharacterDocument('King', true),
                $this->nestedCharacterDocument('Queen', true),
                $this->nestedCharacterDocument('Princess', false),
                $this->nestedCharacterDocument('Lady', false),
            ]);

        $search = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->disableKeywordSearch()
            ->filters('active:true')
            ->queryString('woman')
            ->size(2);

        /** @var SigmieSearchResponse $res */
        $res = $search->get();

        // Verify results
        $hits = $res->hits();
        $totalHits = $res->total();

        // Should only return 2 results (King and Queen), not 1
        $this->assertEquals(2, $totalHits, '');

        // Verify all returned documents have active:true
        foreach ($hits as $hit) {
            $this->assertTrue(
                $hit->_source['active'],
                'All returned documents must have active:true'
            );
        }

        // Verify we get Queen as top result (most relevant to "woman" and "lady")
        $topHit = $res->hits()[0]->_source;
        $this->assertEquals(
            'Queen',
            $topHit['charachter'][0]['details'][0]['meta'][0]['extra'][0]['deep'][0]['deepnote'][0] ?? null,
            'Queen should be the top result for "woman" and "lady" query'
        );

        $this->assertEquals(
            'King',
            $res->hits()[1]->_source['title'] ?? null,
            "King should be the second because it's active compared to lady"
        );
    }

    /**
     * @test
     */
    public function knn_vector_match(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->title('name')->semantic(accuracy: 6, dimensions: 384, api: 'test-embeddings');

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

        $search = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->disableKeywordSearch()
            ->queryString('woman');

        $nestedQuery = $search->makeSearch()->toRaw();

        $this->forElasticsearch(function () use ($nestedQuery): void {
            $this->assertArrayHasKey('knn', $nestedQuery);
        });

        $response = $search->get();

        $hit = $response->json('hits.0._source');

        $this->assertEquals('Queen', $hit['name'] ?? null);
    }

    /**
     * @test
     */
    public function exact_vector_match(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 7, dimensions: 384, api: 'test-embeddings');
        $props->text('text')->semantic(accuracy: 7, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Patient Privacy and Confidentiality Policy',
                'text' => 'Patient privacy and confidentiality are essential for maintaining trust and respect in healthcare.',
            ]),
            new Document([
                'title' => 'Emergency Room Triage Protocol',
                'text' => 'The emergency room triage protocol ensures patients receive timely care based on severity.',
            ]),
        ]);

        $multiSearch = $this->sigmie->newMultiSearch();
        $search = $multiSearch->newSearch($indexName)
            ->index($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->retrieve(['text', 'title'])
            ->queryString('What is the privacy policy?')
            ->size(2);

        // Debug: Get the raw query to inspect
        $rawQuery = $search->makeSearch()->toRaw();

        $this->forElasticsearch(function () use ($rawQuery): void {
            // Elasticsearch uses top-level knn parameter which should be empty for accuracy 7
            $this->assertEmpty($rawQuery['knn'] ?? [], 'KNN should be empty for accuracy 7 in Elasticsearch');
        });

        // Verify function_score is present in the query for both engines
        $queryJson = json_encode($rawQuery);
        $this->assertStringContainsString('function_score', $queryJson, 'Should use function_score for accuracy 7');
        $this->assertStringContainsString('cosineSimilarity', $queryJson, 'Should use cosineSimilarity for accuracy 7');

        $this->assertCount(2, $this->sigmie->collect($indexName, true));
        $this->assertCount(2, $multiSearch->hits());
    }

    /**
     * @test
     */
    public function boosted_semantic_field_uses_dot_product(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('popularity')->float();
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 3, dimensions: 256)->boostedBy('popularity');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $response = $this->sigmie->indexAPICall($indexName, 'GET')->json();
        $key = array_key_first($response);
        $mappings = $response[$key]['mappings']['properties']['_embeddings']['properties']['title']['properties'] ?? [];

        // Find the vector field with dot_product similarity
        $foundDotProduct = false;

        $this->forOpenSearch(function () use ($mappings, &$foundDotProduct): void {
            foreach ($mappings as $field) {
                if (isset($field['method']['space_type']) && $field['method']['space_type'] === 'innerproduct') {
                    $foundDotProduct = true;
                    break;
                }
            }
        });

        $this->forElasticsearch(function () use ($mappings, &$foundDotProduct): void {
            foreach ($mappings as $field) {
                if (isset($field['similarity']) && $field['similarity'] === 'max_inner_product') {
                    $foundDotProduct = true;
                    break;
                }
            }
        });

        $this->assertTrue($foundDotProduct, 'Boosted field should use dot_product similarity');
    }

    /**
     * @test
     */
    public function image_field_uses_l2_norm_similarity(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->image('photo')->semantic(accuracy: 3, dimensions: 256, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $response = $this->sigmie->indexAPICall($indexName, 'GET')->json();
        $key = array_key_first($response);
        $mappings = $response[$key]['mappings']['properties']['_embeddings']['properties']['photo']['properties'] ?? [];

        // Find the vector field with l2_norm similarity
        $foundL2Norm = false;

        $this->forOpenSearch(function () use ($mappings, &$foundL2Norm): void {
            foreach ($mappings as $field) {
                if (isset($field['method']['space_type']) && $field['method']['space_type'] === 'l2') {
                    $foundL2Norm = true;
                    break;
                }
            }
        });

        $this->forElasticsearch(function () use ($mappings, &$foundL2Norm): void {
            foreach ($mappings as $field) {
                if (isset($field['similarity']) && $field['similarity'] === 'l2_norm') {
                    $foundL2Norm = true;
                    break;
                }
            }
        });

        $this->assertTrue($foundL2Norm, 'Image field should use l2_norm similarity');
    }

    /**
     * @test
     */
    public function regular_text_field_uses_cosine_similarity(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 3, dimensions: 256, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $response = $this->sigmie->indexAPICall($indexName, 'GET')->json();
        $key = array_key_first($response);
        $mappings = $response[$key]['mappings']['properties']['_embeddings']['properties']['title']['properties'] ?? [];

        // Find the vector field with cosine similarity
        $foundCosine = false;

        $this->forOpenSearch(function () use ($mappings, &$foundCosine): void {
            foreach ($mappings as $field) {
                if (isset($field['method']['space_type']) && $field['method']['space_type'] === 'cosinesimil') {
                    $foundCosine = true;
                    break;
                }
            }
        });

        $this->forElasticsearch(function () use ($mappings, &$foundCosine): void {
            foreach ($mappings as $field) {
                if (isset($field['similarity']) && $field['similarity'] === 'cosine') {
                    $foundCosine = true;
                    break;
                }
            }
        });

        $this->assertTrue($foundCosine, 'Regular text field should use cosine similarity');
    }

    /**
     * @test
     */
    public function boost_value_scales_vectors(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('boost')->float();
        $props->text('title')
            ->newSemantic(function ($semantic): void {
                $semantic->accuracy(1, 384)
                    ->api('test-embeddings')
                    ->euclideanSimilarity() // Use l2_norm which allows unnormalized vectors
                    ->boostedBy('boost')
                    ->normalizeVector(false); // Disable normalization to see raw scaling
            });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        // Create both documents at once
        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test document',
                    'boost' => 1.0,
                ]),
                new Document([
                    'title' => 'Test document',
                    'boost' => 2.0,
                ]),
            ]);

        // Retrieve both documents
        $docs = $this->sigmie->collect($indexName, true)->take(2);

        $doc1 = $docs[0];
        $doc2 = $docs[1];

        // Get embeddings from both documents
        $embeddings1 = $doc1->_source['_embeddings'];
        $embeddings2 = $doc2->_source['_embeddings'];

        // Get the vector field name
        $vectorFieldName = array_keys($embeddings1['title'])[0];
        $vector1 = $embeddings1['title'][$vectorFieldName];
        $vector2 = $embeddings2['title'][$vectorFieldName];

        // Verify vectors exist
        $this->assertNotEmpty($vector1);
        $this->assertNotEmpty($vector2);
        $this->assertEquals(count($vector1), count($vector2));

        // Check that vector2 is approximately double vector1
        for ($i = 0; $i < min(5, count($vector1)); $i++) {
            $ratio = $vector2[$i] / $vector1[$i];
            $this->assertEqualsWithDelta(2.0, $ratio, 0.001, 'Vector values should be scaled by boost factor');
        }
    }

    /**
     * @test
     */
    public function boost_value_scales_script_score_vectors(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('boost')->float();
        $props->text('title')
            ->newSemantic(function ($semantic): void {
                $semantic->accuracy(7, 384)
                    ->api('test-embeddings')
                    ->euclideanSimilarity()
                    ->boostedBy('boost')
                    ->normalizeVector(false);
            });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test document',
                    'boost' => 1.0,
                ], _id: 'regular-boost'),
                new Document([
                    'title' => 'Test document',
                    'boost' => 2.0,
                ], _id: 'scaled-boost'),
            ]);

        $docs = $this->sigmie->collect($indexName, true)->take(2);

        $embeddings1 = $docs[0]->_source['_embeddings'];
        $embeddings2 = $docs[1]->_source['_embeddings'];
        $vectorFieldName = array_keys($embeddings1['title'])[0];
        $vector1 = $embeddings1['title'][$vectorFieldName][0]['vector'];
        $vector2 = $embeddings2['title'][$vectorFieldName][0]['vector'];

        for ($i = 0; $i < min(5, count($vector1)); $i++) {
            $ratio = $vector2[$i] / $vector1[$i];
            $this->assertEqualsWithDelta(2.0, $ratio, 0.001, 'Script-score vector values should be scaled by boost factor');
        }

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('Test document')
            ->size(2)
            ->hits();

        $this->forElasticsearch(function () use ($hits): void {
            $this->assertSame(['regular-boost', 'scaled-boost'], array_map(fn ($hit): string => $hit->_id, $hits));
        });

        $this->forOpenSearch(function () use ($docs): void {
            $this->assertSame(['regular-boost', 'scaled-boost'], array_map(fn ($doc): string => $doc->_id, $docs));
        });
    }

    /**
     * @test
     */
    public function normalized_boost_value_indexes_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('boost')->float();
        $props->text('title')
            ->newSemantic(function ($semantic): void {
                $semantic->accuracy(1, 384)
                    ->api('test-embeddings')
                    ->boostedBy('boost');
            });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Normalized boost document',
                    'boost' => 1.0,
                ], _id: 'regular-boost'),
                new Document([
                    'title' => 'Normalized boost document',
                    'boost' => 2.0,
                ], _id: 'scaled-boost'),
            ]);

        $docs = $this->sigmie->collect($indexName, true)->take(2);

        foreach ($docs as $doc) {
            $embedding = $doc->_source['_embeddings']['title'];
            $vector = $embedding[array_key_first($embedding)];
            $magnitude = sqrt(array_sum(array_map(fn ($value): int|float => $value * $value, $vector)));

            $this->assertEqualsWithDelta($doc->_source['boost'], $magnitude, 0.001);
        }

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('Normalized boost document')
            ->size(2)
            ->hits();

        $this->forElasticsearch(function () use ($hits): void {
            $this->assertSame(['scaled-boost', 'regular-boost'], array_map(fn ($hit): string => $hit->_id, $hits));
        });

        $this->forOpenSearch(function () use ($docs): void {
            $this->assertSame(['regular-boost', 'scaled-boost'], array_map(fn ($doc): string => $doc->_id, $docs));
        });
    }

    /**
     * @test
     */
    public function normalized_script_score_boost_value_indexes_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('boost')->float();
        $props->text('title')
            ->newSemantic(function ($semantic): void {
                $semantic->accuracy(7, 384)
                    ->api('test-embeddings')
                    ->boostedBy('boost');
            });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Normalized script boost document',
                    'boost' => 1.0,
                ], _id: 'regular-script-boost'),
                new Document([
                    'title' => 'Normalized script boost document',
                    'boost' => 2.0,
                ], _id: 'scaled-script-boost'),
            ]);

        $docs = $this->sigmie->collect($indexName, true)->take(2);

        foreach ($docs as $doc) {
            $embedding = $doc->_source['_embeddings']['title'];
            $vectors = $embedding[array_key_first($embedding)];
            $vector = $vectors[0]['vector'];
            $magnitude = sqrt(array_sum(array_map(fn ($value): int|float => $value * $value, $vector)));

            $this->assertEqualsWithDelta($doc->_source['boost'], $magnitude, 0.001);
        }

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('Normalized script boost document')
            ->size(2)
            ->hits();

        $this->forElasticsearch(function () use ($hits): void {
            $this->assertSame(['scaled-script-boost', 'regular-script-boost'], array_map(fn ($hit): string => $hit->_id, $hits));
        });

        $this->forOpenSearch(function () use ($docs): void {
            $this->assertSame(['regular-script-boost', 'scaled-script-boost'], array_map(fn ($doc): string => $doc->_id, $docs));
        });
    }

    /**
     * @test
     */
    public function missing_semantic_value_still_indexes_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title');
        $props->text('description')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Document without semantic value',
                ], _id: 'missing-semantic-value'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->queryString('Document without semantic value')
            ->hits();

        $this->assertSame(['missing-semantic-value'], array_map(fn ($hit): string => $hit->_id, $hits));
    }

    /**
     * @test
     */
    public function object_semantic_field_indexes_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->object('profile', function (NewProperties $props): void {
            $props->text('bio')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'profile' => [
                        'bio' => 'Elasticsearch semantic profile for alpha',
                    ],
                ], _id: 'alpha-profile'),
                new Document([
                    'profile' => [
                        'bio' => 'Completely different beta content',
                    ],
                ], _id: 'beta-profile'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('Elasticsearch semantic profile', fields: ['profile.bio'])
            ->hits();

        $this->assertSame('alpha-profile', $hits[0]->_id);
    }

    /**
     * @test
     */
    public function missing_nested_semantic_parent_still_indexes_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title');
        $props->nested('comments', function (NewProperties $props): void {
            $props->text('body')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Document without nested comments',
                ], _id: 'missing-nested-parent'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->queryString('Document without nested comments')
            ->hits();

        $this->assertSame(['missing-nested-parent'], array_map(fn ($hit): string => $hit->_id, $hits));
    }

    /**
     * @test
     */
    public function exception_when_boost_field_missing(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not exist in properties');

        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 128)->boostedBy('nonexistent_field');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test',
                ]),
            ]);
    }

    /**
     * @test
     */
    public function exception_when_boost_field_not_number(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must be a Number type');

        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('boost_text');
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 128)->boostedBy('boost_text');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test',
                    'boost_text' => 'not a number',
                ]),
            ]);
    }

    /**
     * @test
     */
    public function exception_when_boost_value_not_in_document(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('is not present in document');

        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('boost')->float();
        $props->text('title')
            ->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 128)
            ->boostedBy('boost');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test',
                    // Missing 'boost' field
                ]),
            ]);
    }

    /**
     * @test
     */
    public function exception_when_boost_value_negative(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must be a positive number');

        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('boost')->float();
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 128)->boostedBy('boost');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test',
                    'boost' => -1.0,
                ]),
            ]);
    }

    /**
     * @test
     */
    public function disable_auto_normalization(): void
    {
        // Create two indices - one with normalization and one without
        $indexWithNorm = uniqid();
        $indexWithoutNorm = uniqid();

        $propsWithNorm = new NewProperties;
        $propsWithNorm->text('title')->newSemantic(function ($semantic): void {
            $semantic->accuracy(2, 384)
                ->api('test-embeddings')
                ->euclideanSimilarity();  // l2_norm with normalization
        });

        $propsWithoutNorm = new NewProperties;
        $propsWithoutNorm->text('title')->newSemantic(function ($semantic): void {
            $semantic->accuracy(2, 384)
                ->api('test-embeddings')
                ->euclideanSimilarity()  // l2_norm without normalization
                ->normalizeVector(false);
        });

        $this->sigmie->newIndex($indexWithNorm)->properties($propsWithNorm)->create();
        $this->sigmie->newIndex($indexWithoutNorm)->properties($propsWithoutNorm)->create();

        // Index the same data in both - use array of strings to trigger averaging
        // IMPORTANT: Use separate Document objects to avoid caching embeddings
        $docData = ['title' => [
            'First sentence about testing',
            'Second sentence about vectors',
            'Third sentence about normalization',
        ]];

        $doc1 = new Document($docData);
        $doc2 = new Document($docData);

        $this->sigmie->collect($indexWithNorm, refresh: true)->properties($propsWithNorm)->merge([$doc1]);
        $this->sigmie->collect($indexWithoutNorm, refresh: true)->properties($propsWithoutNorm)->merge([$doc2]);

        // Get vectors from both indices
        $docsWithNorm = $this->sigmie->collect($indexWithNorm, true)->take(1);
        $docsWithoutNorm = $this->sigmie->collect($indexWithoutNorm, true)->take(1);

        $embeddingsWithNorm = $docsWithNorm[0]->_source['_embeddings']['title'];
        $embeddingsWithoutNorm = $docsWithoutNorm[0]->_source['_embeddings']['title'];

        $vectorFieldName = array_keys($embeddingsWithNorm)[0];
        $vectorWithNorm = $embeddingsWithNorm[$vectorFieldName];
        $vectorWithoutNorm = $embeddingsWithoutNorm[$vectorFieldName];

        // Calculate magnitudes
        $magnitudeWithNorm = sqrt(array_sum(array_map(fn ($v): int|float => $v * $v, $vectorWithNorm)));
        $magnitudeWithoutNorm = sqrt(array_sum(array_map(fn ($v): int|float => $v * $v, $vectorWithoutNorm)));

        // With normalization, magnitude should be ~1.0
        $this->assertEqualsWithDelta(1.0, $magnitudeWithNorm, 0.001, 'Normalized vector should have magnitude ~1.0');

        // Without normalization, magnitudes should be different
        // (averaging without renormalization changes the magnitude)
        $this->assertNotEquals(
            round($magnitudeWithNorm, 3),
            round($magnitudeWithoutNorm, 3),
            'Normalized and unnormalized vectors should have different magnitudes'
        );
    }

    /**
     * @dataProvider exactVectorScores
     */
    public function test_nested_vector_scores_on_the_selected_engine(VectorSimilarity $similarity, float $expected): void
    {
        $driver = $this->elasticsearchConnection->driver();
        $field = $driver->nestedVectorField(new NestedVector('title', 2, similarity: $similarity));
        $index = uniqid('exact-vector-');
        $this->sigmie->indexAPICall($index, 'PUT', [
            'settings' => $driver->indexSettings() ?: (object) [],
            'mappings' => ['properties' => ['_embeddings' => ['properties' => $field->toRaw()]]],
        ]);
        $this->sigmie->indexAPICall($index.'/_doc/one?refresh=true', 'PUT', [
            '_embeddings' => ['title' => [['vector' => [-0.6, 0.8]]]],
        ]);

        $query = $field->vectorQueries([1.0, 0.0], 1, new Boolean)[0]->toRaw();
        $hit = $this->sigmie->rawQuery($index, ['query' => $query])->json('hits.hits.0');
        $this->assertSame('one', $hit['_id']);
        $this->assertEqualsWithDelta($expected, $hit['_score'], 0.00001);
    }

    public static function exactVectorScores(): array
    {
        return [
            [VectorSimilarity::DotProduct, 0.4],
            [VectorSimilarity::MaxInnerProduct, 1 / 1.6],
            [VectorSimilarity::Euclidean, 1 / (1 + sqrt(3.2))],
            [VectorSimilarity::Cosine, 0.4],
        ];
    }

    /**
     * @test
     */
    public function noop_batch_embed_passes_the_text_and_the_field_type(): void
    {
        $noop = new Noop;
        $text = new Text('title');

        $this->assertSame([-1], $noop->embed('hello', $text));
        $this->assertSame([[-1]], $noop->batchEmbed(['hello']));
        $this->assertSame([[-1]], $noop->batchEmbed([['hello', $text]]));
    }

    /**
     * @test
     */
    public function top_level_semantic_zero_is_embedded(): void
    {
        $api = new class implements EmbeddingsApi
        {
            public array $texts = [];

            public function embed(string $text, int $dimensions): array
            {
                return [1.0];
            }

            public function batchEmbed(array $payload): array
            {
                foreach ($payload as $i => $item) {
                    $this->texts[] = $item['text'];
                    $payload[$i]['vector'] = [1.0, 2.0, 3.0];
                }

                return $payload;
            }

            public function promiseEmbed(string $text, int $dimensions): Promise
            {
                $promise = new Promise;
                $promise->resolve(new Response(200));

                return $promise;
            }

            public function model(): string
            {
                return 'fake';
            }

            public function maxBatchSize(): int
            {
                return 10;
            }
        };

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(api: 'fake', accuracy: 1, dimensions: 128);
        $blueprint->nested('comments', function (NewProperties $nested): void {
            $nested->text('body')->semantic(api: 'fake', accuracy: 1, dimensions: 128);
        });

        $processor = new DocumentProcessor($blueprint->get());
        $processor->apis(['fake' => $api]);

        $zero = $processor->populateEmbeddingsBatch([new Document([
            'title' => '0',
            'comments' => [['body' => '0']],
        ])])[0];

        $this->assertSame(['0', '0'], $api->texts);
        $this->assertArrayHasKey('title', $zero->_source['_embeddings']);
        $this->assertArrayHasKey('body', $zero->_source['_embeddings']['comments']);

        $api->texts = [];
        $processor->populateEmbeddingsBatch([new Document([
            'title' => '',
            'comments' => [['body' => '']],
        ])]);
        $this->assertSame([], $api->texts);

        $api->texts = [];
        $processor->populateEmbeddingsBatch([new Document([
            'title' => null,
            'comments' => [['body' => null]],
        ])]);
        $this->assertSame([], $api->texts);
    }

    /**
     * @test
     */
    public function nested_dates_are_formatted_inside_each_item(): void
    {
        $props = new NewProperties;
        $props->date('published');
        $props->datetime('created_at');
        $props->nested('jobs', function (NewProperties $props): void {
            $props->date('day');
            $props->datetime('started_at');
        });

        $at = new DateTime('2024-01-15 12:30:45.123456', new DateTimeZone('UTC'));
        $carbon = Carbon::parse('2024-01-15 12:30:45.123456', 'UTC');
        $processor = new DocumentProcessor($props->get());
        $document = $processor->formatDateTimeFields(new Document([
            'published' => $at,
            'created_at' => $at,
            'jobs' => [
                ['day' => $at, 'started_at' => $at],
                ['day' => $carbon, 'started_at' => $carbon],
            ],
        ]));

        $this->assertSame('2024-01-15', $document->_source['published']);
        $this->assertSame('2024-01-15T12:30:45.123456+00:00', $document->_source['created_at']);
        $this->assertSame('2024-01-15', $document->_source['jobs'][0]['day']);
        $this->assertSame('2024-01-15T12:30:45.123456+00:00', $document->_source['jobs'][0]['started_at']);
        $this->assertSame('2024-01-15', $document->_source['jobs'][1]['day']);
        $this->assertSame('2024-01-15T12:30:45.123456+00:00', $document->_source['jobs'][1]['started_at']);
    }

    /**
     * @test
     */
    public function a_double_nested_semantic_field_embeds_every_value(): void
    {
        $api = new class implements EmbeddingsApi
        {
            public array $texts = [];

            public function embed(string $text, int $dimensions): array
            {
                return [1.0, 0.0, 0.0, 0.0];
            }

            public function batchEmbed(array $payload): array
            {
                foreach ($payload as $item) {
                    $this->texts[] = $item['text'];
                }

                foreach (array_keys($payload) as $key) {
                    $payload[$key]['vector'] = [1.0, 0.0, 0.0, 0.0];
                }

                return $payload;
            }

            public function promiseEmbed(string $text, int $dimensions): Promise
            {
                return new FulfilledPromise(null);
            }

            public function model(): string
            {
                return 'fake';
            }

            public function maxBatchSize(): int
            {
                return 100;
            }
        };

        $props = new NewProperties;
        $props->nested('orders', function (NewProperties $props): void {
            $props->nested('items', function (NewProperties $props): void {
                $props->text('sku')->semantic(api: 'fake', accuracy: 1, dimensions: 128);
            });
        });

        $processor = (new DocumentProcessor($props->get()))->apis(['fake' => $api]);
        $document = $processor->populateEmbeddings(new Document([
            'orders' => [
                ['items' => [['sku' => 'A'], ['sku' => 'B']]],
                ['items' => [['sku' => 'C']]],
            ],
        ]));

        $this->assertSame(['A B C'], $api->texts);
        $this->assertNotEmpty($document->_source['_embeddings']);
    }

    /**
     * @test
     */
    public function a_nested_boost_scales_the_item_vector(): void
    {
        $api = $this->recordingEmbeddings();
        $props = new NewProperties;
        $props->nested('items', function (NewProperties $props): void {
            $props->number('weight')->float();
            $props->text('title')->newSemantic(function ($semantic): void {
                $semantic->accuracy(1, 128)
                    ->api('e')
                    ->boostedBy('items.weight')
                    ->normalizeVector(false);
            });
        });
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $api]);
        $out = $processor->populateEmbeddings(new Document([
            'items' => [
                ['title' => 'Hello', 'weight' => 2],
            ],
        ]));

        $this->assertSame(['Hello'], $api->texts);
        $this->assertSame([2.0, 4.0, 6.0], $this->firstVector($out->_source['_embeddings'] ?? []));
    }

    /**
     * @test
     */
    public function an_object_list_joins_into_one_embedding(): void
    {
        $api = $this->recordingEmbeddings();
        $props = new NewProperties;
        $props->object('profile', function (NewProperties $props): void {
            $props->text('bio')->semantic(api: 'e', dimensions: 128, accuracy: 1);
        });
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $api]);
        $document = new Document([
            'profile' => [
                ['bio' => 'alpha bio'],
                ['bio' => 'beta bio'],
            ],
        ]);

        $processor->validateFields($document);
        $processor->populateEmbeddings($document);

        $this->assertSame(['alpha bio beta bio'], $api->texts);
    }

    /**
     * @test
     */
    public function a_combo_field_under_object_and_nested_is_written(): void
    {
        $props = new NewProperties;
        $props->object('meta', function (NewProperties $inner): void {
            $inner->text('title');
            $inner->combo('full', ['title']);
        });
        $sibling = (new DocumentProcessor($props->get()))->populateComboFields(new Document([
            'meta' => ['title' => 'Hello'],
        ]));
        $this->assertSame('Hello', dot($sibling->_source)->get('meta.full'));

        $props = new NewProperties;
        $props->object('meta', function (NewProperties $inner): void {
            $inner->text('title');
            $inner->combo('full', ['meta.title']);
        });
        $absolute = (new DocumentProcessor($props->get()))->populateComboFields(new Document([
            'meta' => ['title' => 'Hello'],
        ]));
        $this->assertSame('Hello', dot($absolute->_source)->get('meta.full'));

        $api = $this->recordingEmbeddings();
        $props = new NewProperties;
        $props->nested('items', function (NewProperties $inner): void {
            $inner->text('title');
            $inner->combo('full', ['title'])->semantic(api: 'e', dimensions: 128, accuracy: 1);
        });
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $api]);
        $nested = $processor->populateComboFields(new Document([
            'items' => [
                ['title' => 'Hello'],
                ['title' => 'World'],
            ],
        ]));
        $nested = $processor->populateEmbeddings($nested);

        $this->assertSame('Hello', $nested->_source['items'][0]['full']);
        $this->assertSame('World', $nested->_source['items'][1]['full']);
        $this->assertSame(['Hello World'], $api->texts);
    }

    /**
     * @test
     */
    public function an_immutable_datetime_is_formatted(): void
    {
        $props = new NewProperties;
        $props->datetime('published');
        $props->date('on');

        $processor = new DocumentProcessor($props->get());

        $immutable = $processor->formatDateTimeFields(new Document([
            'published' => new DateTimeImmutable('2020-01-02T03:04:05.123456+02:00'),
            'on' => new DateTimeImmutable('2020-05-06'),
        ]));
        $this->assertSame('2020-01-02T03:04:05.123456+02:00', $immutable->_source['published']);
        $this->assertSame('2020-05-06', $immutable->_source['on']);
        $this->assertSame($immutable, $processor->validateFields($immutable));

        $carbon = $processor->formatDateTimeFields(new Document([
            'published' => new CarbonImmutable('2020-01-02T03:04:05.123456+02:00'),
            'on' => new CarbonImmutable('2020-05-06'),
        ]));
        $this->assertSame('2020-01-02T03:04:05.123456+02:00', $carbon->_source['published']);
        $this->assertSame('2020-05-06', $carbon->_source['on']);
        $this->assertSame($carbon, $processor->validateFields($carbon));
    }

    /**
     * @test
     */
    public function noop_satisfies_the_provider_contract(): void
    {
        $props = new NewProperties;
        $props->embeddings(new Noop, 'title');
        $this->assertContains('title', $props->get()->fieldNames());

        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 128);
        $this->assertInstanceOf(Reranker::class, new Reranker(new Noop, $props->get()));
    }

    /**
     * @test
     */
    public function a_combo_field_reads_through_a_list(): void
    {
        $props = new NewProperties;
        $props->nested('authors', function (NewProperties $props): void {
            $props->text('name');
        });
        $props->combo('full', ['authors.name']);

        $out = (new DocumentProcessor($props->get()))->populateComboFields(new Document([
            'authors' => [
                ['name' => 'Ada'],
                ['name' => 'Grace'],
            ],
        ]));

        $this->assertSame('Ada Grace', $out->_source['full'] ?? null);

        $props = new NewProperties;
        $props->nested('orders', function (NewProperties $props): void {
            $props->nested('items', function (NewProperties $props): void {
                $props->text('title');
                $props->combo('full', ['title']);
            });
        });
        $deep = (new DocumentProcessor($props->get()))->populateComboFields(new Document([
            'orders' => [
                ['items' => [['title' => 'A'], ['title' => 'B']]],
                ['items' => [['title' => 'C']]],
            ],
        ]));

        $this->assertSame('A', $deep->_source['orders'][0]['items'][0]['full'] ?? null);
        $this->assertSame('B', $deep->_source['orders'][0]['items'][1]['full'] ?? null);
        $this->assertSame('C', $deep->_source['orders'][1]['items'][0]['full'] ?? null);
    }

    /**
     * @test
     */
    public function an_object_list_of_nested_items_is_embedded(): void
    {
        $api = $this->recordingEmbeddings();
        $props = new NewProperties;
        $props->object('orders', function (NewProperties $props): void {
            $props->nested('items', function (NewProperties $props): void {
                $props->text('sku')->semantic(api: 'e', accuracy: 1, dimensions: 128);
            });
        });
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $api]);
        $document = new Document([
            'orders' => [
                ['items' => [['sku' => 'A'], ['sku' => 'B']]],
                ['items' => [['sku' => 'C']]],
            ],
        ]);
        $processor->validateFields($document);
        $out = $processor->populateEmbeddings($document);

        $this->assertSame(['A B C'], $api->texts);
        $this->assertNotEmpty($out->_source['_embeddings'] ?? null);
    }

    /**
     * @test
     */
    public function a_date_range_bound_is_formatted(): void
    {
        $props = new NewProperties;
        $props->nested('jobs', function (NewProperties $props): void {
            $props->date('day');
            $props->range('span')->date();
        });
        $at = new DateTime('2024-06-15 13:45:30.123456', new DateTimeZone('UTC'));
        $out = (new DocumentProcessor($props->get()))->formatDateTimeFields(new Document([
            'jobs' => [[
                'day' => $at,
                'span' => ['gte' => $at, 'lte' => $at],
            ]],
        ]));

        $this->assertSame('2024-06-15', $out->_source['jobs'][0]['day']);
        $this->assertSame('2024-06-15', $out->_source['jobs'][0]['span']['gte']);
        $this->assertSame('2024-06-15', $out->_source['jobs'][0]['span']['lte']);
    }

    /**
     * @test
     */
    public function a_one_element_boost_list_scales_the_vector(): void
    {
        $api = $this->recordingEmbeddings();
        $props = new NewProperties;
        $props->number('weight')->float();
        $props->text('title')->newSemantic(function ($semantic): void {
            $semantic->accuracy(1, 128)
                ->api('e')
                ->normalizeVector(false)
                ->boostedBy('weight');
        });
        $processor = (new DocumentProcessor($props->get()))->apis(['e' => $api]);
        $document = new Document([
            'title' => 'Hello',
            'weight' => [2],
        ]);
        $processor->validateFields($document);
        $out = $processor->populateEmbeddings($document);

        $this->assertSame([2.0, 4.0, 6.0], $this->firstVector($out->_source['_embeddings'] ?? []));
    }

    /**
     * @test
     */
    public function a_script_score_for_dot_product_stays_non_negative(): void
    {
        $filter = new Boolean;
        $cases = [
            [new ElasticsearchNestedVector('title', 128, VectorSimilarity::DotProduct), "Math.max(0.0, dotProduct(params.query_vector, '_embeddings.title.vector') + 1.0)"],
            [new ElasticsearchNestedVector('title', 128, VectorSimilarity::MaxInnerProduct), "double value = dotProduct(params.query_vector, '_embeddings.title.vector'); return value < 0 ? 1 / (1 + -value) : value + 1;"],
        ];

        foreach ($cases as [$field, $expected]) {
            $raw = $field->vectorQueries([1.0], 10, $filter)[0]->toRaw();
            $this->assertSame($expected, $raw['nested']['query']['function_score']['script_score']['script']['source']);
        }

        $cosine = (new ElasticsearchNestedVector('title', 128, VectorSimilarity::Cosine))->vectorQueries([1.0], 10, $filter)[0]->toRaw();
        $this->assertSame("Math.max(0.0, cosineSimilarity(params.query_vector, '_embeddings.title.vector') + 1.0)", $cosine['nested']['query']['function_score']['script_score']['script']['source']);
    }

    /**
     * @test
     */
    public function a_knn_num_candidates_stays_within_the_limit(): void
    {
        $vector = new DenseVector('title', 128);
        $filter = new Boolean;
        $filter->must()->matchAll();

        $deep = $vector->vectorQueries(array_fill(0, 128, 9.0), 1020, $filter)[0]->toRaw();
        $this->assertSame(1020, $deep['knn']['k']);
        $this->assertSame(10000, $deep['knn']['num_candidates']);

        $small = $vector->vectorQueries(array_fill(0, 128, 9.0), 20, $filter)[0]->toRaw();
        $this->assertSame(20, $small['knn']['k']);
        $this->assertSame(1000, $small['knn']['num_candidates']);
    }

    public function test_knn_k_never_exceeds_the_candidate_limit(): void
    {
        $vector = new DenseVector('title', 128);
        $query = $vector->vectorQueries(array_fill(0, 128, 0.1), 10001, new Boolean)[0]->toRaw()['knn'];

        $this->assertSame(10000, $query['k']);
        $this->assertSame(10000, $query['num_candidates']);
    }

    public function test_boosted_exact_dot_product_preserves_negative_order(): void
    {
        $this->forOpensearch(fn () => $this->markTestSkipped('Elasticsearch exact vector score'));
        $index = uniqid('exact_dot_');
        $props = new NewProperties;
        $props->number('weight');
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 7, dimensions: 128)
            ->boostedBy('weight');
        $field = $props->get()->get('title')->vectorFields()->first()->name;
        $rest = array_fill(0, 127, 0.0);

        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document([
                'title' => 'worse', 'weight' => 3, '_embeddings' => ['title' => [$field => [['vector' => [-3.0, ...$rest]]]]],
            ], _id: 'worse'),
            new Document([
                'title' => 'better', 'weight' => 2, '_embeddings' => ['title' => [$field => [['vector' => [-2.0, ...$rest]]]]],
            ], _id: 'better'),
        ]);

        $hits = $this->sigmie->newSearch($index)->properties($props)->semantic()->disableKeywordSearch()
            ->queryString('guide', vector: [1.0, ...$rest])->get()->hits();

        $this->assertSame(['better', 'worse'], array_column($hits, '_id'));
    }

    public function test_exact_dot_product_ranks_negative_unnormalized_vectors(): void
    {
        $this->forOpensearch(fn () => $this->markTestSkipped('Elasticsearch exact vector score'));
        $index = uniqid('exact_dot_');
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', accuracy: 7, dimensions: 128)
            ->dotProductSimilarity()->normalizeVector(false);
        $field = $props->get()->get('title')->vectorFields()->first()->name;
        $rest = array_fill(0, 127, 0.0);

        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document([
                'title' => 'worse', '_embeddings' => ['title' => [$field => [['vector' => [-3.0, ...$rest]]]]],
            ], _id: 'worse'),
            new Document([
                'title' => 'better', '_embeddings' => ['title' => [$field => [['vector' => [-2.0, ...$rest]]]]],
            ], _id: 'better'),
        ]);

        $hits = $this->sigmie->newSearch($index)->properties($props)->semantic()->disableKeywordSearch()
            ->queryString('guide', vector: [1.0, ...$rest])->get()->hits();

        $this->assertSame(['better', 'worse'], array_column($hits, '_id'));
    }

    /**
     * @test
     */
    public function an_empty_field_list_skips_vector_queries(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);
        $props->text('body')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);
        $vector = array_fill(0, 128, 9.0);

        $empty = $this->semanticBuilder($props)
            ->semantic()
            ->disableKeywordSearch()
            ->fields([])
            ->queryString('q', dimension: 128, vector: $vector)
            ->makeSearch()
            ->toRaw();

        $this->assertArrayNotHasKey('knn', $empty);

        $scoped = $this->semanticBuilder($props)
            ->semantic()
            ->disableKeywordSearch()
            ->fields(['title'])
            ->queryString('q', dimension: 128, vector: $vector, fields: [])
            ->makeSearch()
            ->toRaw();

        $this->assertArrayNotHasKey('knn', $scoped);

        $title = json_encode($this->semanticBuilder($props)
            ->semantic()
            ->disableKeywordSearch()
            ->fields(['title'])
            ->queryString('q', dimension: 128, vector: $vector)
            ->makeSearch()
            ->toRaw());

        $this->assertStringContainsString('_embeddings.title.', $title);
        $this->assertStringNotContainsString('_embeddings.body.', $title);
    }

    /**
     * @test
     */
    public function a_filter_before_properties_stays_on_the_query(): void
    {
        $props = new NewProperties;
        $props->keyword('category');
        $props->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);
        $raw = $this->semanticBuilder(null)
            ->filters("category:'books'")
            ->properties($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('q', dimension: 128, vector: array_fill(0, 128, 9.0))
            ->makeSearch()
            ->toRaw();

        $this->assertStringContainsString('books', json_encode($raw['knn']));
        $this->assertStringContainsString('books', json_encode($raw['query']));
    }

    /**
     * @test
     */
    public function a_sort_and_facets_before_properties_stay_on_the_query(): void
    {
        $props = new NewProperties;
        $props->number('rating');
        $props->keyword('brand');

        $built = $this->semanticBuilder(null)
            ->sort('rating:desc', throwOnError: false)
            ->facets('brand')
            ->properties($props);

        $raw = $built->queryString('q')->makeSearch()->toRaw();

        $this->assertSame([['rating' => 'desc']], $raw['sort']);
        $this->assertSame('brand', $raw['aggs']['brand']['aggs']['brand']['terms']['field']);
        $this->assertSame([], $built->facetParser->errors());
        $this->assertSame([], $built->sortParser->errors());
    }

    /**
     * @test
     */
    public function a_caller_vector_is_used_without_an_api_or_a_dimension(): void
    {
        $vector = array_fill(0, 128, 9.0);
        $props = new NewProperties;
        $props->text('title')->newSemantic(function ($semantic): void {
            $semantic->accuracy(7, 128);
        });
        $json = json_encode($this->semanticBuilder($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('q', dimension: 128, vector: $vector)
            ->makeSearch()
            ->toRaw());

        $this->assertStringContainsString('function_score', $json);
        $this->assertStringContainsString('"query_vector":[9', $json);

        $props = new NewProperties;
        $props->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);
        $raw = $this->semanticBuilder($props)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('hello', vector: $vector)
            ->makeSearch()
            ->toRaw();

        $this->assertSame(9.0, $raw['knn'][0]['query_vector'][0]);
    }

    public function test_disabling_semantic_search_drops_previous_knn_matches(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'test-embeddings', dimensions: 128, accuracy: 3);
        $index = uniqid('switch-semantic-');
        $this->sigmie->newIndex($index)->properties($props)->create();
        $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
            new Document(['title' => 'telephone'], _id: 'one'),
        ]);

        $search = $this->sigmie->newSearch($index)->properties($props)->semantic()
            ->queryString('penguin', vector: array_fill(0, 128, 0.1));

        $this->assertCount(1, $search->hits());
        $this->assertCount(0, $search->semantic(false)->hits());
    }

    private function semanticBuilder(?NewProperties $props, string $engine = 'es'): NewSearch
    {
        $driver = $engine === 'os' ? new Opensearch : new Elasticsearch;
        $connection = new class($driver) extends ElasticsearchConnection
        {
            public function __construct(private SearchEngine $engine) {}

            public function driver(): SearchEngine
            {
                return $this->engine;
            }

            public function isServerless(): bool
            {
                return false;
            }

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponseContract
            {
                throw new RuntimeException('The search must not call Elasticsearch.');
            }

            public function promise(ElasticsearchRequest $request): \Http\Promise\Promise
            {
                throw new RuntimeException('The search must not call Elasticsearch.');
            }
        };

        $api = new class implements EmbeddingsApi
        {
            public array $calls = [];

            public function embed(string $text, int $dimensions): array
            {
                $this->calls[] = $text;

                return array_fill(0, $dimensions, 1.0);
            }

            public function batchEmbed(array $payload): array
            {
                return $payload;
            }

            public function promiseEmbed(string $text, int $dimensions): Promise
            {
                return new FulfilledPromise([1.0]);
            }

            public function model(): string
            {
                return 'probe';
            }

            public function maxBatchSize(): int
            {
                return 100;
            }
        };

        $builder = (new NewSearch($connection))->index('articles')->apis(['embeddings' => $api, 'clip' => $api]);

        if ($props instanceof NewProperties) {
            $builder->properties($props);
        }

        return $builder;
    }

    private function nestedCharacterDocument(string $title, bool $active): Document
    {
        return new Document([
            'active' => $active,
            'title' => $title,
            'charachter' => [[
                'details' => [[
                    'meta' => [[
                        'extra' => [[
                            'deep' => [['deepnote' => [$title]]],
                        ]],
                    ]],
                ]],
            ]],
        ]);
    }

    private function recordingEmbeddings(): EmbeddingsApi
    {
        return new class implements EmbeddingsApi
        {
            public array $texts = [];

            public function embed(string $text, int $dimensions): array
            {
                return [1.0, 2.0, 3.0];
            }

            public function batchEmbed(array $payload): array
            {
                foreach ($payload as $i => $item) {
                    $this->texts[] = $item['text'];
                    $payload[$i]['vector'] = [1.0, 2.0, 3.0];
                }

                return $payload;
            }

            public function promiseEmbed(string $text, int $dimensions): Promise
            {
                return new FulfilledPromise([1.0]);
            }

            public function model(): string
            {
                return 'probe';
            }

            public function maxBatchSize(): int
            {
                return 100;
            }
        };
    }

    private function firstVector(array $node): ?array
    {
        if (array_is_list($node) && $node !== [] && is_numeric($node[0] ?? null)) {
            return $node;
        }

        foreach ($node as $child) {
            if (! is_array($child)) {
                continue;
            }

            $found = $this->firstVector($child);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    public function test_historical_offsets_preserve_the_document_instant(): void
    {
        $properties = new NewProperties;
        $properties->datetime('created_at');

        $date = new DateTime('1900-01-01 12:00:00.123456', new DateTimeZone('Europe/Paris'));
        $document = (new DocumentProcessor($properties->get()))
            ->formatDateTimeFields(new Document(['created_at' => $date]));

        $this->assertSame('1900-01-01T11:50:39.123456+00:00', $document->_source['created_at']);
        $this->assertSame('1900-01-01 12:00:00.123456 Europe/Paris', $date->format('Y-m-d H:i:s.u e'));
    }
}
