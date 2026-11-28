<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\SigmieAnalyticsTool;
use Sigmie\Analytics\AnalyticsRequest;
use Sigmie\Mappings\Types\FlatObject;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\LongText;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Parse\ParseException;
use Sigmie\Tests\Stubs\FakeJsonSchema;

require_once __DIR__.'/Stubs/LaravelAiStubs.php';

use Http\Promise\Promise;
use InvalidArgumentException;
use Laravel\Ai\Tools\Request;
use ReflectionMethod;
use RuntimeException;
use Sigmie\AI\AsTool;
use Sigmie\AI\SigmieFilterValuesTool;
use Sigmie\AI\SigmieGetDocumentsTool;
use Sigmie\AI\SigmieIndexSchemaTool;
use Sigmie\AI\SigmieIndexTool;
use Sigmie\AI\SigmieSampleDocumentsTool;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Document\Document;
use Sigmie\Index\Analysis\Analysis;
use Sigmie\Mappings\Field;
use Sigmie\Mappings\NewProperties;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Search\Contracts\ResponseFormater;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Search\NewSearch;
use Sigmie\Sigmie;
use Sigmie\SigmieIndex;
use Sigmie\Testing\TestCase;
use stdClass;

class SigmieIndexToolTest extends TestCase
{
    /** @withoutCluster */
    public function test_histogram_metric_schema_requests_a_bucket_size(): void
    {
        $schema = (new SigmieAnalyticsTool($this->schemaIndex(new NewProperties)))->schema(new FakeJsonSchema);

        $this->assertStringContainsString('histogram_metric', $schema['bucket_size']->descriptionText);
    }

    /** @withoutCluster */
    public function test_nested_presence_is_filterable_without_keyword_children(): void
    {
        $props = new NewProperties;
        $props->nested('lines', fn (NewProperties $line): LongText => $line->longText('note'));

        $schema = new SigmieIndexSchemaTool($this->schemaIndex($props));
        $field = $schema->result(new Request)['fields'][0];

        $this->assertSame('lines:*', $field['filter']);
        $this->assertTrue($field['filterable']);
    }

    /** @withoutCluster */
    public function test_identifier_filter_examples_use_integer_ids(): void
    {
        $props = new NewProperties;
        $props->id('customer_id');

        $schema = new SigmieIndexSchemaTool($this->schemaIndex($props));

        $this->assertSame('customer_id:42 customer_id:[1,2] customer_id>10', $schema->result(new Request)['fields'][0]['filter']);
    }

    /** @withoutCluster */
    public function test_unique_metric_field_description_accepts_keywords(): void
    {
        $schema = (new SigmieAnalyticsTool($this->schemaIndex(new NewProperties)))->schema(new FakeJsonSchema);

        $this->assertStringContainsString('keyword or numeric field for unique', $schema['field']->descriptionText);
    }

    /** @withoutCluster */
    public function test_date_nanos_is_an_analytics_timeline(): void
    {
        $props = new NewProperties;
        $props->type(new Field('recorded', 'date_nanos'));

        $index = $this->schemaIndex($props);

        $this->assertStringContainsString('Timeline fields for `date_field`: recorded', (new SigmieAnalyticsTool($index))->description());
        $this->assertSame('date', (new SigmieIndexSchemaTool($index))->result(new Request)['fields'][0]['type']);
    }

    /** @withoutCluster */
    public function test_field_names_with_spaces_do_not_advertise_dsl_operations(): void
    {
        $props = new NewProperties;
        $props->keyword('part label');

        $index = $this->schemaIndex($props);
        $field = (new SigmieIndexSchemaTool($index))->result(new Request)['fields'][0];

        $this->assertFalse($field['filterable']);
        $this->assertFalse($field['sortable']);
        $this->assertFalse($field['facetable']);
        $this->assertStringNotContainsString("part label:'value'", (new SigmieIndexTool($index))->description());
    }

    /** @withoutCluster */
    public function test_a_timed_out_search_returns_a_timeout_message(): void
    {
        $index = $this->createStub(SigmieIndex::class);
        $index->method('newSearch')->willThrowException(new ElasticsearchException([
            'json' => ['timed_out' => true], 'request' => ['size' => 10],
        ], 200));

        $error = json_decode((new SigmieIndexTool($index))->handle(new Request(['query' => ''])), true)['error'];

        $this->assertStringContainsString('timed out', $error);
        $this->assertStringNotContainsString('request', $error);
    }

    /** @withoutCluster */
    public function test_range_filter_examples_use_the_mapped_bound_type(): void
    {
        $props = new NewProperties;
        $props->range('dates')->date();
        $props->range('network')->ip();
        $tool = new SigmieIndexSchemaTool($this->schemaIndex($props));

        $this->assertSame(["dates>'2024-01-01'", "network:'192.0.2.1'"], array_column($tool->result(new Request)['fields'], 'filter'));
    }

    /** @withoutCluster */
    public function test_search_names_the_value_discovery_filter_argument(): void
    {
        $tool = new SigmieIndexTool($this->schemaIndex(new NewProperties));

        $this->assertStringContainsString('field name and optional `filters`', $tool->description());
    }

    /** @withoutCluster */
    public function test_disabled_object_children_are_absent_from_queryable_fields(): void
    {
        $children = new NewProperties;
        $children->date('day');

        $props = new NewProperties;
        $props->type(new Object_('archive', $children, ['enabled' => false]));

        $index = $this->schemaIndex($props);

        $this->assertSame([], (new SigmieIndexSchemaTool($index))->result(new Request)['fields']);
        $this->assertStringNotContainsString('archive.day', (new SigmieIndexTool($index))->description());
        $this->assertStringNotContainsString('archive.day', (new SigmieAnalyticsTool($index))->description());
    }

    /** @withoutCluster */
    public function test_flat_object_fields_appear_in_the_queryable_schema(): void
    {
        $props = new NewProperties;
        $props->type(new FlatObject('attributes'));

        $schema = new SigmieIndexSchemaTool($this->schemaIndex($props));

        $this->assertSame([[
            'name' => 'attributes', 'type' => 'flat_object', 'filterable' => true,
            'sortable' => true, 'facetable' => false, 'filter' => "attributes:'value'",
        ]], $schema->result(new Request)['fields']);
    }

    /** @withoutCluster */
    public function test_document_id_schema_matches_the_retrieval_limit(): void
    {
        $tool = new SigmieGetDocumentsTool($this->schemaIndex(new NewProperties));
        $ids = $tool->schema(new FakeJsonSchema)['ids'];

        $this->assertSame([1, 100, 1], [$ids->minimum, $ids->maximum, $ids->itemsType->minimum]);
    }

    /** @withoutCluster */
    public function test_sample_schema_matches_the_sample_limits(): void
    {
        $tool = new SigmieSampleDocumentsTool($this->schemaIndex(new NewProperties));
        $limit = $tool->schema(new FakeJsonSchema)['limit'];

        $this->assertSame([1, 20], [$limit->minimum, $limit->maximum]);
    }

    /** @withoutCluster */
    public function test_search_schema_uses_one_based_pages_and_nonnegative_sizes(): void
    {
        $schema = (new SigmieIndexTool($this->schemaIndex(new NewProperties)))->schema(new FakeJsonSchema);

        $this->assertSame(1, $schema['page']->minimum);
        $this->assertSame(0, $schema['per_page']->minimum);
    }

    /** @withoutCluster */
    public function test_value_discovery_schema_bounds_the_terms_size(): void
    {
        $tool = new SigmieFilterValuesTool($this->schemaIndex(new NewProperties));
        $limit = $tool->schema(new FakeJsonSchema)['limit'];

        $this->assertSame([0, 2147483647], [$limit->minimum, $limit->maximum]);
    }

    /** @withoutCluster */
    public function test_analytics_schema_advertises_the_request_bounds(): void
    {
        $schema = (new SigmieAnalyticsTool($this->schemaIndex(new NewProperties)))->schema(new FakeJsonSchema);

        foreach ([
            'limit' => [1, 100], 'hit_limit' => [1, 100], 'precision' => [1, 12],
            'timezone_offset' => [-840, 840], 'include_hits' => [0, 1], 'bucket_aliases_only' => [0, 1],
            'bucket_size' => [1, null], 'min_count' => [0, null], 'min_doc_count' => [0, null],
        ] as $key => $bounds) {
            $this->assertSame($bounds, [$schema[$key]->minimum, $schema[$key]->maximum], $key);
        }
    }

    /** @withoutCluster */
    public function test_analytics_schema_lists_supported_choices(): void
    {
        $schema = (new SigmieAnalyticsTool($this->schemaIndex(new NewProperties)))->schema(new FakeJsonSchema);

        $this->assertSame(AnalyticsRequest::WIDGETS, $schema['widget']->choices);
        $this->assertContains('unique', $schema['metric']->choices);
        $this->assertContains(null, $schema['metric']->choices);
        $this->assertContains('today', $schema['range']->choices);
        $this->assertContains(null, $schema['range']->choices);
    }

    /** @withoutCluster */
    public function test_deeply_nested_value_discovery_reports_omitted_buckets(): void
    {
        $props = new NewProperties;
        $props->nested('orders', function (NewProperties $orders): void {
            $orders->nested('items', fn (NewProperties $items): Keyword => $items->keyword('sku'));
        });
        $field = 'orders.items.sku';
        $index = $this->capturingIndex($props, rawAggregations: [
            $field => ['_nested' => ['_nested' => [
                $field => [$field => [
                    'sum_other_doc_count' => 2,
                    'buckets' => [['key' => 'book', 'doc_count' => 5, '_documents' => ['doc_count' => 3]]],
                ]],
            ]]],
        ]);

        $result = (new SigmieFilterValuesTool($index))->result(new Request(['field' => $field, 'limit' => 1]));

        $this->assertSame([
            'field' => $field,
            'values' => ['book' => 3],
            'truncated' => true,
            'other_documents' => 2,
        ], $result);
    }

    /** @withoutCluster */
    public function test_nested_search_description_uses_the_mapped_filter_fields(): void
    {
        $props = new NewProperties;
        $props->nested('orders', function (NewProperties $orders): void {
            $orders->keyword('status');
            $orders->nested('items', fn (NewProperties $items): Keyword => $items->keyword('sku'));
        });

        $description = (new SigmieIndexTool($this->schemaIndex($props)))->description();

        $this->assertStringContainsString("orders:{status:'value' AND items:{sku:'value'}}", $description);
    }

    /** @withoutCluster */
    public function test_search_tool_registers_the_name_used_by_companion_tools(): void
    {
        $index = $this->capturingIndex(new NewProperties);
        $tool = new SigmieIndexTool($index);
        $this->assertStringContainsString($tool->name(), (new SigmieGetDocumentsTool($index))->description());
    }

    public function test_sample_returns_the_document_id_and_source(): void
    {
        $index = $this->createScopedProductIndex();
        [, , $sample] = $index->tools();

        $row = json_decode($sample->handle(new Request(['limit' => 1, 'fields' => 'name'])), true, flags: JSON_THROW_ON_ERROR)[0];

        $this->assertSame(['_id', '_source'], array_keys($row));
        $this->assertSame(['name'], array_keys($row['_source']));
    }

    public static array $facetRaw = [];

    public static string $sentSort = '';

    public static string $sentFacets = '';

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
        $comment = (new ReflectionMethod($this, $this->getName(false)))->getDocComment();

        return is_string($comment) && str_contains($comment, '@withoutCluster');
    }

    /** @test */
    public function facet_filters_narrow_hits_without_requesting_buckets(): void
    {
        $index = $this->createProductIndex();
        $index->merge([
            new Document(['brand' => 'One']),
            new Document(['brand' => 'Two']),
        ], refresh: true);

        $result = (new SigmieIndexTool($index))->result(new Request([
            'query' => '',
            'facet_filters' => "brand:'Two'",
        ]));

        $this->assertSame(1, $result['total']);
        $this->assertSame('Two', $result['hits'][0]['brand']);
        $this->assertArrayNotHasKey('facets', $result);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_public_geo_sort_uses_the_entire_field_name(): void
    {
        $props = new NewProperties;
        $props->geoPoint('store-location');
        $props->geoPoint('location');

        $tool = new SigmieIndexTool($this->capturingIndex($props, except: ['location']));
        $sort = 'store-location[40,-74]:km:asc';

        $tool->result(new Request(['query' => '', 'sort' => $sort]));

        $this->assertSame($sort, self::$sentSort);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function nested_filter_examples_preserve_child_groups(): void
    {
        $props = new NewProperties;
        $props->nested('orders', function (NewProperties $orders): void {
            $orders->nested('items', function (NewProperties $items): void {
                $items->keyword('sku');
                $items->keyword('color');
            });
        });
        $schema = (new SigmieIndexSchemaTool($this->schemaIndex($props)))->result(new Request([]));

        $this->assertSame("orders:{items:{sku:'value' AND color:'value'}}", $schema['fields'][0]['filter']);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function value_discovery_validates_filters_at_zero_limit(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');

        $tool = new SigmieFilterValuesTool($this->capturingIndex($props));
        $output = json_decode($tool->handle(new Request(['field' => 'brand', 'filters' => 'absent:red', 'limit' => 0])), true);

        $this->assertStringContainsString('Field absent does not exist', $output['error'] ?? '');
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_retrieved_document_preserves_a_source_field_named_zero(): void
    {
        $props = new NewProperties;
        $props->keyword('0');

        $index = $this->capturingIndex($props, [
            'total' => ['value' => 1],
            'hits' => [['_id' => 'item', '_source' => ['0' => 'zero']]],
        ]);
        $result = (new SigmieGetDocumentsTool($index))->handle(new Request(['ids' => ['item']]));

        $this->assertSame('{"0":"zero"}', json_encode(json_decode($result)[0]->_source));
    }

    /** @test */
    public function a_parent_facet_filter_narrows_nested_facet_counts(): void
    {
        $index = $this->createArticlesIndex();
        $index->merge([new Document([
            'author' => 'Someone else',
            'sections' => [['type' => 'other']],
        ])], refresh: true);

        $result = (new SigmieIndexTool($index))->result(new Request([
            'query' => '',
            'facets' => 'sections.type',
            'facet_filters' => "author:'Jane Doe'",
        ]));

        $this->assertSame(1, $result['total']);
        $this->assertEquals(['analysis' => 1, 'conclusion' => 1, 'summary' => 1], $result['facets']['sections.type']);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_field_named_sum_other_doc_count_returns_the_actual_omitted_count(): void
    {
        $name = 'sum_other_doc_count';
        $props = new NewProperties;
        $props->keyword($name);

        $index = $this->capturingIndex($props, rawAggregations: [
            $name => ['doc_count' => 5, $name => [
                'sum_other_doc_count' => 2,
                'buckets' => [['key' => 'a', 'doc_count' => 3]],
            ]],
        ]);
        $result = (new SigmieFilterValuesTool($index))->result(new Request(['field' => $name, 'limit' => 1]));

        $this->assertSame(['a' => 3], $result['values']);
        $this->assertSame(2, $result['other_documents']);
    }

    /** @test */
    public function default_output_fields_include_source_fields_outside_the_blueprint(): void
    {
        $index = $this->createProductIndex();
        $index->merge([new Document(['brand' => 'One', 'label' => 'Kept'], 'product')], refresh: true);

        $search = (new SigmieIndexTool($index))->result(new Request(['query' => '', 'fields' => null]));
        $documents = (new SigmieGetDocumentsTool($index))->result(new Request(['ids' => ['product'], 'fields' => null]));

        $this->assertSame('Kept', $search['hits'][0]['label'] ?? null);
        $this->assertSame('Kept', $documents[0]->_source['label'] ?? null);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function value_discovery_rejects_facet_parameters_in_the_field_name(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');

        $tool = new SigmieFilterValuesTool($this->capturingIndex($props));
        $result = json_decode($tool->handle(new Request(['field' => 'brand:5'])), true);

        $this->assertStringContainsString('Field brand:5 does not exist', $result['error'] ?? '');
    }

    private function createProductIndex(): SigmieIndex
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            use AsTool;

            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->name('name');
                $props->category('brand');
                $props->number('price');
                $props->bool('in_stock');
                $props->date('created_at');

                return $props;
            }
        };
        $index->create();

        return $index;
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function matching_items_accept_a_hyphenated_nested_field(): void
    {
        $props = new NewProperties;
        $props->nested('line-items', static function (NewProperties $props): void {
            $props->text('title');
        });
        $tool = new SigmieIndexTool($this->capturingIndex($props));
        $result = $tool->result(new Request(['query' => 'book', 'matches' => 'line-items.title:2']));
        $this->assertSame([], $result['hits']);
    }

    /**
     * @test
     */
    public function description_contains_index_name(): void
    {
        $index = $this->createProductIndex();

        $tool = new SigmieIndexTool($index);

        $this->assertStringContainsString($index->name(), $tool->description());
    }

    /**
     * @test
     */
    public function description_contains_all_field_names(): void
    {
        $index = $this->createProductIndex();

        $tool = new SigmieIndexTool($index);
        $description = $tool->description();

        $this->assertStringContainsString('name', $description);
        $this->assertStringContainsString('brand', $description);
        $this->assertStringContainsString('price', $description);
        $this->assertStringContainsString('in_stock', $description);
        $this->assertStringContainsString('created_at', $description);
    }

    /**
     * @test
     */
    public function description_contains_field_types(): void
    {
        $index = $this->createProductIndex();

        $tool = new SigmieIndexTool($index);
        $description = $tool->description();

        $this->assertStringContainsString('[text]', $description);
        $this->assertStringContainsString('[number]', $description);
        $this->assertStringContainsString('[boolean]', $description);
        $this->assertStringContainsString('[date]', $description);
    }

    /**
     * @test
     */
    public function description_contains_filter_examples(): void
    {
        $index = $this->createProductIndex();

        $tool = new SigmieIndexTool($index);
        $description = $tool->description();

        // Keyword filter syntax
        $this->assertStringContainsString("brand:'value'", $description);
        $this->assertStringContainsString("brand:['a','b']", $description);

        // Number filter syntax
        $this->assertStringContainsString('price>n', $description);
        $this->assertStringContainsString('price:min..max', $description);

        // Boolean filter syntax
        $this->assertStringContainsString('in_stock:true', $description);
        $this->assertStringContainsString('in_stock:false', $description);

        // Date filter syntax
        $this->assertStringContainsString("created_at>'2024-01-01'", $description);
    }

    /**
     * @test
     */
    public function description_contains_operator_docs(): void
    {
        $index = $this->createProductIndex();

        $tool = new SigmieIndexTool($index);
        $description = $tool->description();

        $this->assertStringContainsString('AND, OR, AND NOT', $description);
        $this->assertStringContainsString('NOT', $description);
        $this->assertStringContainsString('Grouping', $description);
        $this->assertStringContainsString('field:*', $description);
    }

    /**
     * @test
     */
    public function description_shows_sortable_capability(): void
    {
        $index = $this->createProductIndex();

        $tool = new SigmieIndexTool($index);
        $description = $tool->description();

        $this->assertStringContainsString('sortable', $description);
    }

    /**
     * @test
     */
    public function description_shows_facetable_capability(): void
    {
        $index = $this->createProductIndex();

        $tool = new SigmieIndexTool($index);
        $description = $tool->description();

        $this->assertStringContainsString('facetable', $description);
    }

    /**
     * @test
     */
    public function description_handles_nested_fields(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->name('name');
                $props->nested('variants', function (NewProperties $p): void {
                    $p->keyword('color');
                    $p->number('size');
                });

                return $props;
            }
        };

        $index->create();

        $tool = new SigmieIndexTool($index);
        $description = $tool->description();

        $this->assertStringContainsString('[nested]', $description);
        $this->assertStringContainsString('variants:', $description);
        $this->assertStringContainsString('Sub-fields', $description);
        $this->assertStringContainsString('color', $description);
        $this->assertStringContainsString('size', $description);
    }

    /**
     * @test
     */
    public function description_handles_object_fields_with_dot_notation(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->object('meta', function (NewProperties $p): void {
                    $p->keyword('author');
                });

                return $props;
            }
        };

        $index->create();

        $tool = new SigmieIndexTool($index);
        $description = $tool->description();

        $this->assertStringContainsString('meta.author', $description);
    }

    /**
     * @test
     */
    public function handle_searches_and_returns_hits(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-02-20']),
            new Document(['name' => 'Pixel', 'brand' => 'Google', 'price' => 699, 'in_stock' => false, 'created_at' => '2024-03-10']),
        ], refresh: true);

        $tool = new SigmieIndexTool($index);

        $result = json_decode($tool->handle(new Request([
            'query' => 'iPhone',
        ])), true);

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('hits', $result);
        $this->assertGreaterThan(0, $result['total']);
        $this->assertEquals('iPhone', $result['hits'][0]['name']);
    }

    /**
     * @test
     */
    public function handle_applies_filters(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'MacBook', 'brand' => 'Apple', 'price' => 1999, 'in_stock' => true, 'created_at' => '2024-02-20']),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-03-10']),
        ], refresh: true);

        $tool = new SigmieIndexTool($index);

        $result = json_decode($tool->handle(new Request([
            'query' => '',
            'filters' => "brand:'Apple'",
        ])), true);

        $this->assertEquals(2, $result['total']);

        foreach ($result['hits'] as $hit) {
            $this->assertEquals('Apple', $hit['brand']);
        }
    }

    /**
     * @test
     */
    public function handle_applies_base_filters(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-03-10']),
            new Document(['name' => 'Pixel', 'brand' => 'Google', 'price' => 699, 'in_stock' => false, 'created_at' => '2024-03-10']),
        ], refresh: true);

        $tool = new SigmieIndexTool($index, baseFilters: "brand:'Apple'");

        $result = json_decode($tool->handle(new Request([
            'query' => '',
        ])), true);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals('Apple', $result['hits'][0]['brand']);
    }

    /**
     * @test
     */
    public function handle_combines_base_filters_with_ai_filters(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'MacBook', 'brand' => 'Apple', 'price' => 1999, 'in_stock' => false, 'created_at' => '2024-02-20']),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-03-10']),
        ], refresh: true);

        $tool = new SigmieIndexTool($index, baseFilters: "brand:'Apple'");

        $result = json_decode($tool->handle(new Request([
            'query' => '',
            'filters' => 'in_stock:true',
        ])), true);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals('iPhone', $result['hits'][0]['name']);
    }

    /**
     * @test
     */
    public function ai_filters_with_unbalanced_parentheses_cannot_escape_base_filters(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-03-10']),
        ], refresh: true);

        $escape = "brand:'Google') OR (brand:'Samsung'";

        $search = new SigmieIndexTool($index, baseFilters: "brand:'Apple'");
        $values = new SigmieFilterValuesTool($index, baseFilters: "brand:'Apple'");

        $searchResult = json_decode($search->handle(new Request(['query' => '', 'filters' => $escape])), true);
        $valuesResult = json_decode($values->handle(new Request(['field' => 'brand', 'filters' => $escape])), true);

        $this->assertNotContains('Samsung', array_column($searchResult['hits'] ?? [], 'brand'));
        $this->assertArrayNotHasKey('Samsung', (array) ($valuesResult['values'] ?? []));
        $this->assertArrayHasKey('error', $searchResult);
        $this->assertArrayHasKey('error', $valuesResult);

        $this->expectException(ParseException::class);

        $search->result(new Request(['query' => '', 'filters' => $escape]));
    }

    /**
     * @test
     */
    public function handle_applies_sort(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-03-10']),
            new Document(['name' => 'Pixel', 'brand' => 'Google', 'price' => 699, 'in_stock' => true, 'created_at' => '2024-02-20']),
        ], refresh: true);

        $tool = new SigmieIndexTool($index);

        $result = json_decode($tool->handle(new Request([
            'query' => '',
            'sort' => 'price:asc',
        ])), true);

        $this->assertEquals(699, $result['hits'][0]['price']);
        $this->assertEquals(999, $result['hits'][2]['price']);
    }

    /**
     * @test
     */
    public function handle_paginates_results(): void
    {
        $index = $this->createProductIndex();

        $documents = [];
        for ($i = 1; $i <= 5; $i++) {
            $documents[] = new Document([
                'name' => 'Product '.$i,
                'brand' => 'Brand',
                'price' => $i * 100,
                'in_stock' => true,
                'created_at' => '2024-01-01',
            ]);
        }

        $index->merge($documents, refresh: true);

        $tool = new SigmieIndexTool($index);

        $result = json_decode($tool->handle(new Request([
            'query' => '',
            'per_page' => 2,
            'page' => 1,
        ])), true);

        $this->assertEquals(5, $result['total']);
        $this->assertCount(2, $result['hits']);
    }

    /**
     * @test
     */
    public function handle_returns_facets_when_requested(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'MacBook', 'brand' => 'Apple', 'price' => 1999, 'in_stock' => true, 'created_at' => '2024-02-20']),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-03-10']),
        ], refresh: true);

        $tool = new SigmieIndexTool($index);

        $result = json_decode($tool->handle(new Request([
            'query' => '',
            'facets' => 'brand',
        ])), true);

        $this->assertArrayHasKey('facets', $result);
        $this->assertArrayHasKey('brand', $result['facets']);
    }

    /**
     * @test
     */
    public function handle_returns_no_facets_key_when_not_requested(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
        ], refresh: true);

        $tool = new SigmieIndexTool($index);

        $result = json_decode($tool->handle(new Request([
            'query' => '',
        ])), true);

        $this->assertArrayNotHasKey('facets', $result);
    }

    /**
     * @test
     */
    public function hits_contain_id_and_source_fields(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
        ], refresh: true);

        $tool = new SigmieIndexTool($index);

        $result = json_decode($tool->handle(new Request([
            'query' => '',
        ])), true);

        $hit = $result['hits'][0];

        $this->assertArrayHasKey('_id', $hit);
        $this->assertArrayHasKey('name', $hit);
        $this->assertArrayHasKey('brand', $hit);
        $this->assertArrayHasKey('price', $hit);
    }

    /**
     * @test
     */
    public function as_tool_trait_returns_sigmie_index_tool(): void
    {
        $index = $this->createProductIndex();

        $tool = $index->tools()[0];

        $this->assertInstanceOf(SigmieIndexTool::class, $tool);
    }

    /**
     * @test
     */
    public function as_tool_trait_passes_base_filters(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-03-10']),
        ], refresh: true);

        $tool = $index->tools("brand:'Apple'")[0];

        $result = json_decode($tool->handle(new Request([
            'query' => '',
        ])), true);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals('Apple', $result['hits'][0]['brand']);
    }

    /**
     * @test
     */
    public function description_contains_facet_docs(): void
    {
        $index = $this->createProductIndex();

        $tool = new SigmieIndexTool($index);
        $description = $tool->description();

        $this->assertStringContainsString('Facets', $description);
        $this->assertStringContainsString('Sort', $description);
        $this->assertStringContainsString('Geo sort', $description);
    }

    /**
     * @test
     */
    public function description_marks_text_only_fields_as_query_only(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->longText('body');

                return $props;
            }
        };

        $index->create();

        $tool = new SigmieIndexTool($index);
        $description = $tool->description();

        $this->assertStringContainsString('query only', $description);
    }

    /**
     * @test
     */
    public function description_includes_field_description(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            use AsTool;

            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->keyword('country_code_a3')
                    ->description('Country as ISO-3166 alpha-3, e.g. DEU=Germany.');

                return $props;
            }
        };
        $index->create();

        $description = (new SigmieIndexTool($index))->description();

        $this->assertStringContainsString('Country as ISO-3166 alpha-3, e.g. DEU=Germany.', $description);
    }

    /**
     * @test
     */
    public function field_description_is_not_sent_to_elasticsearch(): void
    {
        $field = (new Keyword('country_code_a3'))
            ->description('Country as ISO-3166 alpha-3, e.g. DEU=Germany.');

        $raw = json_encode($field->toRaw());

        $this->assertStringNotContainsString('ISO-3166', $raw);
    }

    /**
     * @test
     */
    public function tools_returns_search_values_sample_and_schema_tools(): void
    {
        $index = $this->createProductIndex();

        [$search, $values, $sample, $get, $schema] = $index->tools();

        $this->assertInstanceOf(SigmieIndexTool::class, $search);
        $this->assertInstanceOf(SigmieFilterValuesTool::class, $values);
        $this->assertInstanceOf(SigmieSampleDocumentsTool::class, $sample);
        $this->assertInstanceOf(SigmieGetDocumentsTool::class, $get);
        $this->assertInstanceOf(SigmieIndexSchemaTool::class, $schema);
    }

    /**
     * @test
     */
    public function describe_index_returns_structured_fields_and_syntax(): void
    {
        $index = $this->createProductIndex();

        $result = json_decode((new SigmieIndexSchemaTool($index))->handle(new Request([])), true);

        $this->assertEquals($index->name(), $result['index']);
        $this->assertArrayHasKey('fields', $result);
        $this->assertArrayHasKey('filter_syntax', $result);
        $this->assertArrayHasKey('notes', $result);

        $byName = [];
        foreach ($result['fields'] as $field) {
            $byName[$field['name']] = $field;
        }

        $this->assertEqualsCanonicalizing(
            ['name', 'brand', 'price', 'in_stock', 'created_at'],
            array_keys($byName)
        );

        $this->assertTrue($byName['brand']['filterable']);
        $this->assertStringContainsString("brand:'value'", $byName['brand']['filter']);

        $this->assertSame('number', $byName['price']['type']);
        $this->assertTrue($byName['price']['sortable']);
        $this->assertTrue($byName['price']['filterable']);

        // The case-sensitivity guidance must be present for the agent.
        $this->assertStringContainsString(
            'case-sensitive',
            implode(' ', $result['notes'])
        );
    }

    /**
     * @test
     */
    public function describe_index_includes_field_descriptions(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            use AsTool;

            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->keyword('country_code_a3')
                    ->description('Country as ISO-3166 alpha-3, e.g. DEU=Germany.');

                return $props;
            }
        };
        $index->create();

        $result = json_decode((new SigmieIndexSchemaTool($index))->handle(new Request([])), true);

        $field = null;
        foreach ($result['fields'] as $candidate) {
            if ($candidate['name'] === 'country_code_a3') {
                $field = $candidate;
                break;
            }
        }

        $this->assertSame('Country as ISO-3166 alpha-3, e.g. DEU=Germany.', $field['description']);
    }

    /**
     * @test
     */
    public function filter_values_lists_distinct_term_values(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'MacBook', 'brand' => 'Apple', 'price' => 1999, 'in_stock' => true, 'created_at' => '2024-02-20']),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-03-10']),
        ], refresh: true);

        $result = json_decode((new SigmieFilterValuesTool($index))->handle(new Request(['field' => 'brand'])), true);

        $this->assertEquals('brand', $result['field']);
        $this->assertArrayHasKey('Apple', $result['values']);
        $this->assertArrayHasKey('Samsung', $result['values']);
    }

    /**
     * @test
     */
    public function filter_values_narrows_by_filters(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-03-10']),
        ], refresh: true);

        $result = json_decode((new SigmieFilterValuesTool($index))->handle(new Request(['field' => 'brand', 'filters' => 'brand:App*'])), true);

        $this->assertArrayHasKey('Apple', $result['values']);
        $this->assertArrayNotHasKey('Samsung', $result['values']);
    }

    /**
     * @test
     */
    public function filter_values_reports_truncation_when_more_values_exist_than_the_limit(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->keyword('color');

                return $props;
            }
        };
        $index->create();

        $index->merge([
            new Document(['color' => 'red']),
            new Document(['color' => 'red']),
            new Document(['color' => 'blue']),
            new Document(['color' => 'green']),
        ], refresh: true);

        $tool = new SigmieFilterValuesTool($index);

        $partial = json_decode($tool->handle(new Request(['field' => 'color', 'limit' => 2])), true);

        $this->assertCount(2, $partial['values']);
        $this->assertTrue($partial['truncated']);
        $this->assertSame(1, $partial['other_documents']);

        $complete = json_decode($tool->handle(new Request(['field' => 'color', 'limit' => 3])), true);

        $this->assertCount(3, $complete['values']);
        $this->assertFalse($complete['truncated']);
        $this->assertSame(0, $complete['other_documents']);
    }

    /**
     * @test
     */
    public function filter_values_returns_min_max_for_numeric_fields(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'Pixel', 'brand' => 'Google', 'price' => 699, 'in_stock' => true, 'created_at' => '2024-02-20']),
            new Document(['name' => 'MacBook', 'brand' => 'Apple', 'price' => 1999, 'in_stock' => true, 'created_at' => '2024-03-10']),
        ], refresh: true);

        $result = json_decode((new SigmieFilterValuesTool($index))->handle(new Request(['field' => 'price'])), true);

        $this->assertEquals(699, $result['values']['min']);
        $this->assertEquals(1999, $result['values']['max']);
    }

    /**
     * @test
     */
    public function filter_values_returns_date_range_for_date_fields(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'Pixel', 'brand' => 'Google', 'price' => 699, 'in_stock' => true, 'created_at' => '2024-02-20']),
            new Document(['name' => 'MacBook', 'brand' => 'Apple', 'price' => 1999, 'in_stock' => true, 'created_at' => '2024-03-10']),
        ], refresh: true);

        $tool = new SigmieFilterValuesTool($index);

        $all = json_decode($tool->handle(new Request(['field' => 'created_at'])), true);
        $google = json_decode($tool->handle(new Request(['field' => 'created_at', 'filters' => "brand:'Google'"])), true);

        $this->assertSame(['count' => 3, 'min' => '2024-01-15T00:00:00.000Z', 'max' => '2024-03-10T00:00:00.000Z'], $all['values']);
        $this->assertSame(['count' => 1, 'min' => '2024-02-20T00:00:00.000Z', 'max' => '2024-02-20T00:00:00.000Z'], $google['values']);
    }

    /**
     * @test
     */
    public function filter_values_handle_surfaces_unknown_field_as_error(): void
    {
        $index = $this->createProductIndex();

        // handle() must NOT throw — an unknown/non-facetable field is returned as a readable
        // {"error": ...} so the agent can read it and correct its arguments, instead of the
        // whole turn aborting on an uncaught exception.
        $result = json_decode((new SigmieFilterValuesTool($index))->handle(new Request(['field' => 'nope'])), true);

        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['error']);
    }

    /**
     * @test
     */
    public function filter_values_result_still_throws_on_unknown_field(): void
    {
        $index = $this->createProductIndex();

        // result() keeps normal exception semantics for programmatic callers.
        $this->expectException(ParseException::class);

        (new SigmieFilterValuesTool($index))->result(new Request(['field' => 'nope']));
    }

    /**
     * @test
     */
    public function sample_documents_returns_documents(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-03-10']),
        ], refresh: true);

        $result = json_decode((new SigmieSampleDocumentsTool($index))->handle(new Request(['limit' => 2])), true);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('_id', $result[0]);
        $this->assertArrayHasKey('_source', $result[0]);
    }

    /**
     * @test
     */
    public function get_documents_returns_requested_documents_by_id(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15'], 'doc_1'),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-03-10'], 'doc_2'),
            new Document(['name' => 'Pixel', 'brand' => 'Google', 'price' => 699, 'in_stock' => false, 'created_at' => '2024-04-01'], 'doc_3'),
        ], refresh: true);

        // Requests two existing ids and one that does not exist; missing ids are omitted.
        $result = json_decode((new SigmieGetDocumentsTool($index))->handle(new Request(['ids' => ['doc_1', 'doc_3', 'missing']])), true);

        $this->assertCount(2, $result);
        $this->assertSame(['doc_1', 'doc_3'], array_column($result, '_id'));
        $this->assertSame('iPhone', $result[0]['_source']['name']);
    }

    /**
     * @test
     */
    public function sample_documents_only_samples_documents_inside_the_base_filter(): void
    {
        $index = $this->createScopedProductIndex();

        $result = (new SigmieSampleDocumentsTool($index, "brand:'Apple'"))->result(new Request(['limit' => 20]));

        $this->assertSame(['doc_1', 'doc_3'], $this->sortedIds($result));
    }

    /**
     * @test
     */
    public function get_documents_treats_an_id_outside_the_base_filter_as_missing(): void
    {
        $index = $this->createScopedProductIndex();
        $tool = new SigmieGetDocumentsTool($index, "brand:'Apple'");

        $result = json_decode($tool->handle(new Request(['ids' => ['doc_3', 'doc_2', 'doc_1', 'missing']])), true);

        $this->assertSame(['doc_3', 'doc_1'], array_column($result, '_id'));
        $this->assertSame('iMac', $result[0]['_source']['name']);
        $this->assertSame('[]', $tool->handle(new Request(['ids' => ['doc_2']])));
        $this->assertSame('[]', $tool->handle(new Request(['ids' => ['missing']])));
    }

    /**
     * @test
     */
    public function as_tool_trait_scopes_every_tool_with_the_base_filter(): void
    {
        $index = $this->createScopedProductIndex();

        [$search, $values, $sample, $get, $schema, $analytics] = $index->tools("brand:'Apple'");

        $outputs = [
            $search->handle(new Request(['query' => ''])),
            $values->handle(new Request(['field' => 'brand'])),
            $sample->handle(new Request(['limit' => 20])),
            $get->handle(new Request(['ids' => ['doc_1', 'doc_2', 'doc_3']])),
            $schema->handle(new Request([])),
            $analytics->handle(new Request([
                'widget' => 'table',
                'date_field' => 'created_at',
                'fields' => 'name,brand',
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ])),
        ];

        foreach ($outputs as $output) {
            $this->assertStringNotContainsString('Samsung', $output);
            $this->assertStringNotContainsString('Galaxy', $output);
        }

        $this->assertSame(['doc_1', 'doc_3'], $this->sortedIds(json_decode($outputs[2], true)));
        $this->assertSame(['doc_1', 'doc_3'], $this->sortedIds(json_decode($outputs[3], true)));
    }

    /**
     * @test
     */
    public function get_documents_tool_schema_requires_an_ids_array(): void
    {
        $index = $this->createProductIndex();

        $schema = (new SigmieGetDocumentsTool($index))->schema(new FakeJsonSchema);

        $this->assertSame(['ids', 'fields'], array_keys($schema));
        $this->assertTrue($schema['fields']->nullable);
        $this->assertSame('array', $schema['ids']->type);
        $this->assertTrue($schema['ids']->required);
        $this->assertSame('string', $schema['ids']->itemsType->type);
    }

    /**
     * @test
     */
    public function result_returns_an_array_not_a_json_string(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
        ], refresh: true);

        $search = (new SigmieIndexTool($index))->result(new Request(['query' => '']));
        $this->assertIsArray($search);
        $this->assertArrayHasKey('total', $search);
        $this->assertArrayHasKey('hits', $search);

        $values = (new SigmieFilterValuesTool($index))->result(new Request(['field' => 'brand']));
        $this->assertIsArray($values);
        $this->assertArrayHasKey('values', $values);

        $sample = (new SigmieSampleDocumentsTool($index))->result(new Request(['limit' => 1]));
        $this->assertIsArray($sample);
    }

    /**
     * @test
     */
    public function handle_surfaces_malformed_sort_as_error(): void
    {
        $index = $this->createProductIndex();

        // SQL-style "price asc" (a space instead of "price:asc") makes the sort parser throw.
        // handle() must return it as an {"error": ...} the agent can correct, not propagate
        // an exception that aborts the turn — and the message must show the correct syntax.
        $result = json_decode((new SigmieIndexTool($index))->handle(new Request([
            'query' => '',
            'sort' => 'price asc',
        ])), true);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('field:asc', $result['error']);
    }

    /**
     * @test
     */
    public function handle_surfaces_malformed_filter_as_error(): void
    {
        $index = $this->createProductIndex();

        $result = json_decode((new SigmieIndexTool($index))->handle(new Request([
            'query' => '',
            'filters' => '(brand:',
        ])), true);

        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['error']);
    }

    /**
     * @test
     */
    public function result_still_throws_on_malformed_filter(): void
    {
        $index = $this->createProductIndex();

        // result() keeps normal exception semantics; only handle() is guarded.
        $this->expectException(ParseException::class);

        (new SigmieIndexTool($index))->result(new Request([
            'query' => '',
            'filters' => '(brand:',
        ]));
    }

    /**
     * @test
     */
    public function handle_surfaces_unknown_filter_field_as_error(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
        ], refresh: true);

        // An unknown field must reach the agent as an error, not a silent match-none with total 0.
        $result = json_decode((new SigmieIndexTool($index))->handle(new Request([
            'query' => '',
            'filters' => "color:'red'",
        ])), true);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('color', $result['error']);
    }

    /**
     * @test
     */
    public function handle_surfaces_unparseable_filter_as_error(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
        ], refresh: true);

        $result = json_decode((new SigmieIndexTool($index))->handle(new Request([
            'query' => '',
            'filters' => 'price=999',
        ])), true);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('price=999', $result['error']);
    }

    /**
     * @test
     */
    public function handle_surfaces_unknown_facet_field_as_error(): void
    {
        $index = $this->createProductIndex();

        $result = json_decode((new SigmieIndexTool($index))->handle(new Request([
            'query' => '',
            'facets' => 'color',
        ])), true);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('color', $result['error']);
    }

    /**
     * @test
     */
    public function handle_surfaces_unknown_facet_filter_field_as_error(): void
    {
        $index = $this->createProductIndex();

        $result = json_decode((new SigmieIndexTool($index))->handle(new Request([
            'query' => '',
            'facets' => 'brand',
            'facet_filters' => "color:'red'",
        ])), true);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('color', $result['error']);
    }

    /**
     * @test
     */
    public function filter_values_handle_surfaces_unknown_filter_field_as_error(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
        ], refresh: true);

        $result = json_decode((new SigmieFilterValuesTool($index))->handle(new Request([
            'field' => 'brand',
            'filters' => "color:'red'",
        ])), true);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('color', $result['error']);
    }

    /**
     * OpenAI's strict function-calling rejects schemas with optional properties. To stay
     * compatible across providers, every AI tool in Sigmie marks each optional param both
     * `required()` AND `nullable()` — so the property is in `required[]` (OpenAI happy) and
     * the caller can pass null when it doesn't want a value (Anthropic-equivalent UX).
     *
     * @test
     */
    public function search_tool_schema_marks_every_property_required_for_openai_strict(): void
    {
        $index = $this->createProductIndex();

        $schema = (new SigmieIndexTool($index))->schema(new FakeJsonSchema);

        $expected = ['query', 'filters', 'sort', 'facets', 'facet_filters', 'per_page', 'page', 'fields', 'matches'];
        $this->assertSame($expected, array_keys($schema));

        foreach ($schema as $name => $prop) {
            $this->assertTrue(
                $prop->required,
                sprintf("Property '%s' must be required() for OpenAI strict-mode function calling.", $name)
            );
        }

        // `query` stays a plain required string; everything else is nullable so the LLM can pass null.
        $this->assertFalse($schema['query']->nullable, "Property 'query' must NOT be nullable.");
        foreach (['filters', 'sort', 'facets', 'facet_filters', 'per_page', 'page'] as $name) {
            $this->assertTrue(
                $schema[$name]->nullable,
                sprintf("Optional property '%s' must be nullable() for OpenAI strict-mode function calling.", $name)
            );
        }

        // Defaults on the int params are still declared so the model knows the fallback.
        $this->assertSame(10, $schema['per_page']->defaultValue);
        $this->assertSame(1, $schema['page']->defaultValue);
    }

    /**
     * @test
     */
    public function filter_values_tool_schema_is_openai_strict_compatible(): void
    {
        $index = $this->createProductIndex();

        $schema = (new SigmieFilterValuesTool($index))->schema(new FakeJsonSchema);

        $this->assertSame(['field', 'filters', 'limit'], array_keys($schema));

        foreach ($schema as $name => $prop) {
            $this->assertTrue($prop->required, sprintf("Property '%s' must be required().", $name));
        }

        $this->assertFalse($schema['field']->nullable);
        $this->assertTrue($schema['filters']->nullable);
        $this->assertTrue($schema['limit']->nullable);
    }

    /**
     * @test
     */
    public function sample_documents_tool_schema_is_openai_strict_compatible(): void
    {
        $index = $this->createProductIndex();

        $schema = (new SigmieSampleDocumentsTool($index))->schema(new FakeJsonSchema);

        $this->assertSame(['limit', 'fields'], array_keys($schema));
        $this->assertTrue($schema['fields']->required);
        $this->assertTrue($schema['fields']->nullable);
        $this->assertTrue($schema['limit']->required);
        $this->assertTrue($schema['limit']->nullable);
        $this->assertSame(5, $schema['limit']->defaultValue);
    }

    /**
     * Sanity check: handle() still works when the LLM passes null for every optional param,
     * which is what an OpenAI strict-mode caller does when it has no value to supply.
     *
     * @test
     */
    public function handle_accepts_null_for_every_optional_param(): void
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15']),
        ], refresh: true);

        $result = json_decode((new SigmieIndexTool($index))->handle(new Request([
            'query' => 'iPhone',
            'filters' => null,
            'sort' => null,
            'facets' => null,
            'facet_filters' => null,
            'per_page' => null,
            'page' => null,
        ])), true);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertArrayHasKey('hits', $result);
        $this->assertGreaterThan(0, $result['total']);
    }

    /**
     * @test
     */
    public function schema_tool_metadata_and_nested_object_schema_are_backed_by_elasticsearch_hits(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->name('name');
                $props->object('meta', function (NewProperties $props): void {
                    $props->keyword('author')->description('Document author.');
                });
                $props->nested('variants', function (NewProperties $props): void {
                    $props->keyword('color');
                    $props->number('size');
                });

                return $props;
            }
        };

        $index->create();
        $index->merge([
            new Document([
                'name' => 'Nested schema guide',
                'meta' => ['author' => 'Nico'],
                'variants' => [
                    ['color' => 'blue', 'size' => 42],
                ],
            ], 'schema-guide'),
        ], refresh: true);

        $hits = $index->newSearch()
            ->queryString('Nested schema')
            ->hits();

        $this->assertSame(['schema-guide'], array_map(fn ($hit): string => $hit->_id, $hits));

        $schema = new SigmieIndexSchemaTool($index);
        $filterValues = new SigmieFilterValuesTool($index);
        $getDocuments = new SigmieGetDocumentsTool($index);
        $sampleDocuments = new SigmieSampleDocumentsTool($index);

        $this->assertSame('describe_index_'.$index->name(), $schema->name());
        $this->assertStringContainsString($index->name(), $schema->description());
        $this->assertSame([], $schema->schema(new FakeJsonSchema));
        $this->assertSame('discover_filter_values_'.$index->name(), $filterValues->name());
        $this->assertStringContainsString($index->name(), $filterValues->description());
        $this->assertSame('get_documents_'.$index->name(), $getDocuments->name());
        $this->assertStringContainsString($index->name(), $getDocuments->description());
        $this->assertSame('sample_documents_'.$index->name(), $sampleDocuments->name());
        $this->assertStringContainsString($index->name(), $sampleDocuments->description());

        $result = $schema->result(new Request([]));
        $fields = [];
        foreach ($result['fields'] as $field) {
            $fields[$field['name']] = $field;
        }

        $this->assertSame('keyword', $fields['meta.author']['type']);
        $this->assertSame('Document author.', $fields['meta.author']['description']);
        $this->assertSame('nested', $fields['variants']['type']);
        $this->assertSame('color', $fields['variants']['subfields'][0]['name']);
        $this->assertSame('size', $fields['variants']['subfields'][1]['name']);
    }

    /**
     * Two Apple documents (doc_1, doc_3) inside a `brand:'Apple'` scope and one Samsung document (doc_2) outside it.
     */
    private function createScopedProductIndex(): SigmieIndex
    {
        $index = $this->createProductIndex();

        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple', 'price' => 999, 'in_stock' => true, 'created_at' => '2024-01-15'], 'doc_1'),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung', 'price' => 899, 'in_stock' => true, 'created_at' => '2024-03-10'], 'doc_2'),
            new Document(['name' => 'iMac', 'brand' => 'Apple', 'price' => 1299, 'in_stock' => true, 'created_at' => '2024-04-01'], 'doc_3'),
        ], refresh: true);

        return $index;
    }

    private function sortedIds(array $documents): array
    {
        $ids = array_column(json_decode(json_encode($documents), true), '_id');
        sort($ids);

        return $ids;
    }

    private function createArticlesIndex(): SigmieIndex
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            use AsTool;

            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->title('title');
                $props->category('author');
                $props->text('body');
                $props->nested('sections', function (NewProperties $props): void {
                    $props->keyword('type');
                    $props->text('text');
                    $props->keyword('internal_note');
                });

                return $props;
            }

            public function toolFields(): array
            {
                return ['title', 'author'];
            }

            public function exceptFromTools(): array
            {
                return ['sections.internal_note'];
            }
        };

        $index->create();
        $index->merge([
            new Document([
                'title' => 'Energy at home',
                'author' => 'Jane Doe',
                'body' => 'FULL ARTICLE BODY about solar power.',
                'sections' => [
                    ['type' => 'summary', 'text' => 'Summary of the article.', 'internal_note' => 'NOTE-0'],
                    ['type' => 'analysis', 'text' => 'The solar panels were installed.', 'internal_note' => 'NOTE-1'],
                    ['type' => 'analysis', 'text' => 'The solar inverter was replaced.', 'internal_note' => 'NOTE-2'],
                    ['type' => 'conclusion', 'text' => 'Solar power pays off in six years.', 'internal_note' => 'NOTE-3'],
                    ['type' => 'analysis', 'text' => 'A solar battery stores the surplus.', 'internal_note' => 'NOTE-4'],
                    ['type' => 'analysis', 'text' => 'The heat pump was kept.', 'internal_note' => 'NOTE-5'],
                ],
            ], 'article-1'),
        ], refresh: true);

        return $index;
    }

    /**
     * @test
     */
    public function document_tools_return_only_the_requested_fields_including_nested_paths(): void
    {
        [$search, , $sample, $get] = $this->createArticlesIndex()->tools();

        $documents = [
            $search->result(new Request(['query' => '', 'fields' => 'title, sections.type']))['hits'][0],
            json_decode($sample->handle(new Request(['limit' => 1, 'fields' => 'title, sections.type'])), true)[0]['_source'],
            json_decode($get->handle(new Request(['ids' => ['article-1'], 'fields' => 'title, sections.type'])), true)[0]['_source'],
        ];

        foreach ($documents as $document) {
            unset($document['_id']);

            $this->assertEqualsCanonicalizing(['title', 'sections'], array_keys($document));
            $this->assertSame(['type' => 'summary'], $document['sections'][0]);
        }
    }

    /**
     * @test
     */
    public function document_tools_use_the_index_tool_fields_when_fields_is_null(): void
    {
        [$search, , $sample, $get] = $this->createArticlesIndex()->tools();

        $documents = [
            $search->result(new Request(['query' => '', 'fields' => null]))['hits'][0],
            json_decode($sample->handle(new Request(['limit' => 1, 'fields' => null])), true)[0]['_source'],
            json_decode($get->handle(new Request(['ids' => ['article-1'], 'fields' => null])), true)[0]['_source'],
        ];

        foreach ($documents as $document) {
            unset($document['_id']);

            $this->assertEqualsCanonicalizing(['title', 'author'], array_keys($document));
        }

        $this->assertStringContainsString('Pass null for the default: title,author.', $search->schema(new FakeJsonSchema)['fields']->descriptionText);
    }

    /**
     * @test
     */
    public function document_tools_return_a_field_outside_the_default_when_requested(): void
    {
        [$search, , $sample, $get] = $this->createArticlesIndex()->tools();

        $documents = [
            $search->result(new Request(['query' => '', 'fields' => 'body']))['hits'][0],
            json_decode($sample->handle(new Request(['limit' => 1, 'fields' => 'body'])), true)[0]['_source'],
            json_decode($get->handle(new Request(['ids' => ['article-1'], 'fields' => 'body'])), true)[0]['_source'],
        ];

        foreach ($documents as $document) {
            $this->assertSame('FULL ARTICLE BODY about solar power.', $document['body']);
            $this->assertArrayNotHasKey('title', $document);
        }
    }

    /**
     * @test
     */
    public function fields_excepted_from_tools_are_never_returned_even_when_requested(): void
    {
        [$search, , $sample, $get] = $this->createPrivateContactsIndex()->tools();

        $fields = 'title,secret,contacts.name,contacts.email';

        $outputs = [
            $search->handle(new Request(['query' => 'Premium', 'fields' => $fields])),
            $sample->handle(new Request(['limit' => 5, 'fields' => $fields])),
            $get->handle(new Request(['ids' => ['customer-1'], 'fields' => $fields])),
        ];

        foreach ($outputs as $output) {
            $this->assertStringContainsString('Jane', $output);

            foreach (['SECRET-VALUE', 'jane@example.com', 'john@example.com'] as $private) {
                $this->assertStringNotContainsString($private, $output);
            }
        }
    }

    /**
     * @test
     */
    public function search_returns_the_matching_nested_items_of_the_requested_paths(): void
    {
        [$search] = $this->createArticlesIndex()->tools();

        $this->assertArrayNotHasKey('_matches', $search->result(new Request(['query' => 'solar', 'matches' => null]))['hits'][0]);

        $hit = $search->result(new Request(['query' => 'solar', 'fields' => null, 'matches' => 'sections.text']))['hits'][0];

        $this->assertArrayNotHasKey('sections', $hit);
        $this->assertSame(4, $hit['_matches']['sections']['total']);
        $this->assertEqualsCanonicalizing([1, 2, 3, 4], array_column($hit['_matches']['sections']['items'], '_offset'));

        foreach ($hit['_matches']['sections']['items'] as $section) {
            $this->assertEqualsCanonicalizing(['_offset', 'text'], array_keys($section));
            $this->assertStringContainsStringIgnoringCase('solar', $section['text']);
        }
    }

    /**
     * @test
     */
    public function search_matches_accept_a_size_per_path(): void
    {
        [$search] = $this->createArticlesIndex()->tools();

        foreach (['sections.text:2', 'sections.type:9, sections.text:2'] as $matches) {
            $sections = $search->result(new Request(['query' => 'solar', 'matches' => $matches]))['hits'][0]['_matches']['sections'];

            $this->assertSame(4, $sections['total']);
            $this->assertCount(2, $sections['items']);
        }

        foreach (['sections.text:0', 'sections.text:101', 'sections.text:two'] as $matches) {
            $output = json_decode($search->handle(new Request(['query' => 'solar', 'matches' => $matches])), true);

            $this->assertStringContainsString(
                sprintf("Invalid matches entry '%s'. Use a nested path, optionally with ':size' from 1 to 100", $matches),
                $output['error'] ?? ''
            );
        }
    }

    /**
     * @test
     */
    public function search_matches_follow_a_nested_filter_and_never_return_fields_excepted_from_tools(): void
    {
        [$search] = $this->createArticlesIndex()->tools();

        $output = $search->handle(new Request([
            'query' => '',
            'filters' => "sections:{type:'conclusion'}",
            'matches' => 'sections',
        ]));

        $this->assertEquals(
            ['total' => 1, 'items' => [['_offset' => 3, 'type' => 'conclusion', 'text' => 'Solar power pays off in six years.']]],
            json_decode($output, true)['hits'][0]['_matches']['sections']
        );
        $this->assertStringNotContainsString('NOTE-', $output);
    }

    private function createPrivateContactsIndex(): SigmieIndex
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            use AsTool;

            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->name('title');
                $props->keyword('secret');
                $props->category('region');
                $props->nested('contacts', function (NewProperties $props): void {
                    $props->keyword('name');
                    $props->keyword('email');
                    $props->keyword('phone');
                });

                return $props;
            }

            public function exceptFromTools(): array
            {
                return ['secret', 'contacts.email', 'contacts.phone'];
            }
        };

        $index->create();
        $index->merge([
            new Document([
                'title' => 'Premium account',
                'secret' => 'SECRET-VALUE',
                'region' => 'North',
                'contacts' => [
                    ['name' => 'Jane', 'email' => 'jane@example.com', 'phone' => '555-0101'],
                    ['name' => 'John', 'email' => 'john@example.com', 'phone' => '555-0102'],
                ],
            ], 'customer-1'),
            new Document([
                'title' => 'Basic account',
                'secret' => 'OTHER-SECRET',
                'region' => 'South',
                'contacts' => [
                    ['name' => 'Max', 'email' => 'max@example.com', 'phone' => '555-0103'],
                ],
            ], 'customer-2'),
        ], refresh: true);

        return $index;
    }

    /**
     * @test
     */
    public function document_tools_never_return_fields_excepted_from_tools(): void
    {
        [$search, , $sample, $get] = $this->createPrivateContactsIndex()->tools();

        $outputs = [
            $search->handle(new Request([
                'query' => 'Premium',
                'filters' => "contacts:{email:'jane@example.com'}",
            ])),
            $sample->handle(new Request(['limit' => 5])),
            $get->handle(new Request(['ids' => ['customer-1']])),
        ];

        foreach ($outputs as $output) {
            $this->assertStringContainsString('Jane', $output);
            $this->assertStringContainsString('Premium account', $output);

            foreach (['SECRET-VALUE', 'jane@example.com', 'john@example.com', '555-0101', '555-0102'] as $private) {
                $this->assertStringNotContainsString($private, $output);
            }
        }
    }

    /**
     * @test
     */
    public function a_search_whose_fields_leave_no_source_returns_the_ids(): void
    {
        [$search] = $this->createPrivateContactsIndex()->tools();

        $result = $search->result(new Request(['query' => '', 'fields' => 'secret', 'sort' => 'title:asc']));

        $this->assertSame([['_id' => 'customer-2'], ['_id' => 'customer-1']], $result['hits']);
    }

    /**
     * @test
     */
    public function documents_with_numeric_ids_are_retrieved_by_integer_ids(): void
    {
        $index = $this->createProductIndex();
        $index->merge([
            new Document(['name' => 'iPhone', 'brand' => 'Apple'], '7'),
            new Document(['name' => 'Galaxy', 'brand' => 'Samsung'], '8'),
        ], refresh: true);

        $documents = json_decode((new SigmieGetDocumentsTool($index))->handle(new Request(['ids' => [8, '7']])), true);

        $this->assertSame(['8', '7'], array_column($documents, '_id'));
    }

    /**
     * @test
     */
    public function a_page_whose_offset_overflows_is_a_readable_error(): void
    {
        [$search] = $this->createPrivateContactsIndex()->tools();

        $output = json_decode($search->handle(new Request(['query' => '', 'page' => PHP_INT_MAX, 'per_page' => 10])), true);

        $this->assertStringStartsWith(sprintf('Page %d of 10 results is out of range.', PHP_INT_MAX), $output['error'] ?? '');
    }

    /**
     * @test
     */
    public function a_request_elasticsearch_cannot_parse_does_not_echo_the_base_filter(): void
    {
        [$search] = $this->createPrivateContactsIndex()->tools("secret:'SECRET-VALUE'");

        $output = $search->handle(new Request(['query' => '', 'page' => 2147483647, 'per_page' => 100]));

        $this->assertStringContainsString('out of range of int', json_decode($output, true)['error'] ?? '');
        $this->assertStringNotContainsString('SECRET-VALUE', $output);
    }

    /**
     * @test
     */
    public function a_malformed_base_filter_throws_instead_of_reaching_the_agent(): void
    {
        $index = $this->createProductIndex();
        $scope = "brand:'Apple' AND (";

        $calls = [
            [new SigmieIndexTool($index, baseFilters: $scope), ['query' => '']],
            [new SigmieFilterValuesTool($index, baseFilters: $scope), ['field' => 'brand']],
            [new SigmieSampleDocumentsTool($index, baseFilters: $scope), ['limit' => 1]],
            [new SigmieGetDocumentsTool($index, baseFilters: $scope), ['ids' => ['1']]],
            [new SigmieAnalyticsTool($index, baseFilters: $scope), ['widget' => 'kpi', 'metric' => 'count', 'date_field' => 'created_at', 'from' => '2024-01-01', 'to' => '2024-02-01']],
        ];

        $thrown = 0;
        foreach ($calls as [$tool, $arguments]) {
            try {
                $tool->handle(new Request($arguments));
            } catch (ParseException) {
                $thrown++;
            }
        }

        $this->assertSame(5, $thrown);
    }

    /**
     * @test
     */
    public function tools_never_return_stored_vectors(): void
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            use AsTool;

            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->text('title')->semantic(api: 'test-embeddings', accuracy: 1, dimensions: 384);
                $props->date('created_at');

                return $props;
            }
        };

        $index->create();
        $index->merge([new Document(['title' => 'Red running shoes', 'created_at' => '2024-01-02'], 'shoes')], refresh: true);

        [$search, , $sample, $get, , $analytics] = $index->tools();
        $window = ['date_field' => 'created_at', 'from' => '2024-01-01', 'to' => '2024-01-04'];

        $outputs = [
            $search->handle(new Request(['query' => '', 'fields' => null])),
            $search->handle(new Request(['query' => '', 'fields' => 'title,_embeddings'])),
            $sample->handle(new Request(['limit' => 5])),
            $get->handle(new Request(['ids' => ['shoes']])),
            $analytics->handle(new Request(['widget' => 'table', ...$window])),
            $analytics->handle(new Request(['widget' => 'kpi', 'metric' => 'count', 'include_hits' => 1, ...$window])),
        ];

        foreach ($outputs as $output) {
            $this->assertStringContainsString('Red running shoes', $output);
            $this->assertStringNotContainsString('_embeddings', $output);
        }
    }

    /**
     * @test
     *
     * @dataProvider listingsOfFieldsExceptedFromTools
     */
    public function tools_refuse_to_list_facet_or_sort_fields_excepted_from_tools(int $tool, array $arguments, string $privateField): void
    {
        $output = $this->createPrivateContactsIndex()->tools()[$tool]->handle(new Request($arguments));

        $this->assertStringContainsString(
            sprintf('Field %s is private and cannot be listed, grouped, faceted or sorted; you can still filter on it.', $privateField),
            json_decode($output, true)['error'] ?? ''
        );

        foreach (['SECRET-VALUE', 'OTHER-SECRET', 'jane@example.com', 'john@example.com', 'max@example.com'] as $private) {
            $this->assertStringNotContainsString($private, $output);
        }
    }

    /**
     * @return array<string, array{0: int, 1: array<string, mixed>, 2: string}>
     */
    public static function listingsOfFieldsExceptedFromTools(): array
    {
        $id = 'contacts.email';

        return [
            'discover_filter_values' => [1, ['field' => $id], $id],
            'discover_filter_values on a filtered private field' => [1, ['field' => $id, 'filters' => "contacts:{email:'jane@example.com'}"], $id],
            'discover_filter_values on a top-level private field' => [1, ['field' => 'secret'], 'secret'],
            'search facets' => [0, ['query' => '', 'facets' => 'region '.$id.':10'], $id],
            'search facets with facet_filters' => [0, ['query' => '', 'facets' => $id, 'facet_filters' => $id.":'jane@example.com'"], $id],
            'search sort' => [0, ['query' => '', 'sort' => 'secret:asc'], 'secret'],
        ];
    }

    /**
     * @test
     */
    public function fields_excepted_from_tools_still_filter_and_are_described_as_filter_only(): void
    {
        $index = $this->createPrivateContactsIndex();
        [$search, $values, , , $schema] = $index->tools();

        $regions = $values->result(new Request([
            'field' => 'region',
            'filters' => "contacts:{email:'jane@example.com'}",
        ]));

        $this->assertSame(['North'], array_keys((array) $regions['values']));

        $hits = $search->result(new Request(['query' => '', 'filters' => "secret:'OTHER-SECRET'", 'facets' => 'region']));

        $this->assertSame(['customer-2'], array_column($hits['hits'], '_id'));
        $this->assertSame(['South'], array_keys((array) $hits['facets']['region']));

        $this->assertStringContainsString('- secret [keyword] (filter only):', $search->description());
        $this->assertStringContainsString('email [keyword] (filter only):', $search->description());
        $this->assertStringContainsString('Private fields (filter only', $values->description());

        $fields = array_column($schema->result(new Request([]))['fields'], null, 'name');
        $subfields = array_column($fields['contacts']['subfields'], null, 'name');

        $this->assertTrue($fields['secret']['filter_only']);
        $this->assertFalse($fields['secret']['facetable']);
        $this->assertFalse($fields['secret']['sortable']);
        $this->assertTrue($subfields['email']['filter_only']);
        $this->assertArrayNotHasKey('filter_only', $subfields['name']);
        $this->assertArrayNotHasKey('filter_only', $fields['region']);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_price_facet_keeps_the_default_histogram_interval(): void
    {
        $props = new NewProperties;
        $props->price('price');
        $props->keyword('brand');

        $index = $this->capturingIndex($props);

        (new SigmieFilterValuesTool($index))->result(new Request(['field' => 'price', 'limit' => 5]));
        $limit5 = self::$facetRaw;

        (new SigmieFilterValuesTool($index))->result(new Request(['field' => 'price']));
        $default = self::$facetRaw;

        (new SigmieFilterValuesTool($index))->result(new Request(['field' => 'brand', 'limit' => 5]));
        $brand = json_encode(self::$facetRaw);

        $this->assertSame(10, $this->histogramInterval($limit5));
        $this->assertSame(10, $this->histogramInterval($default));
        $this->assertSame(5, $this->termsSize($brand, 'brand'));
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_nested_object_field_stays_in_the_search_description(): void
    {
        $index = new class extends SigmieIndex
        {
            public function __construct() {}

            public function name(): string
            {
                return 'orders';
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->nested('orders', function (NewProperties $props): void {
                    $props->keyword('id');
                    $props->object('customer', function (NewProperties $props): void {
                        $props->keyword('city');
                    });
                });

                return $props;
            }
        };

        $description = (new SigmieIndexTool($index))->description();
        $schema = (new SigmieIndexSchemaTool($index))->result(new Request([]));
        $names = [];
        foreach ($schema['fields'] as $field) {
            foreach ($field['subfields'] ?? [] as $sub) {
                $names[] = $sub['name'].':'.$sub['type'];
            }
        }

        $this->assertStringContainsString('customer.city [keyword]', $description);
        $this->assertStringContainsString('id [keyword]', $description);
        $this->assertContains('customer.city:keyword', $names);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_source_id_does_not_replace_the_hit_id(): void
    {
        $props = new NewProperties;
        $props->keyword('title');

        $index = $this->capturingIndex($props, [
            'total' => ['value' => 1],
            'hits' => [[
                '_id' => 'doc-1',
                '_score' => 1.5,
                '_source' => [
                    '_id' => 'from-source',
                    '_matches' => ['bogus' => true],
                    'title' => 'Hello',
                ],
            ]],
        ]);

        $result = (new SigmieIndexTool($index))->result(new Request([
            'query' => 'Hello',
            'matches' => null,
        ]));

        $this->assertSame([
            'total' => 1,
            'hits' => [[
                '_id' => 'doc-1',
                'title' => 'Hello',
            ]],
        ], $result);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_hit_field_named_one_stays_on_the_search_tool(): void
    {
        $props = new NewProperties;
        $props->keyword('1');

        $index = $this->capturingIndex($props, [
            'total' => ['value' => 1],
            'hits' => [[
                '_id' => 'doc-1',
                '_score' => 1,
                '_source' => [
                    '1' => 'kept',
                    '2' => 'also',
                    '0' => 'zero',
                ],
            ]],
        ]);

        $result = (new SigmieIndexTool($index))->result(new Request([
            'query' => 'kept',
        ]));
        $json = json_encode($result['hits'][0]);

        $this->assertStringContainsString('"1":"kept"', $json);
        $this->assertStringContainsString('"2":"also"', $json);
        $this->assertStringContainsString('"0":"zero"', $json);
        $this->assertSame('doc-1', $result['hits'][0]['_id']);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function documents_keep_the_first_requested_id_order(): void
    {
        $props = new NewProperties;
        $props->keyword('name');

        $index = $this->capturingIndex($props, [
            'total' => ['value' => 2],
            'hits' => [
                ['_id' => 'a', '_index' => 'products', '_score' => 1, '_source' => ['name' => 'A']],
                ['_id' => 'b', '_index' => 'products', '_score' => 1, '_source' => ['name' => 'B']],
            ],
        ]);

        $ids = static fn (array $documents): array => array_column(json_decode(json_encode($documents), true), '_id');
        $tool = new SigmieGetDocumentsTool($index);

        $this->assertSame(['b', 'a'], $ids($tool->result(new Request(['ids' => ['b', 'a']]))));
        $this->assertSame(['b', 'a'], $ids($tool->result(new Request(['ids' => ['b', 'a', 'b']]))));
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_nested_field_line_keeps_its_description(): void
    {
        $index = new class extends SigmieIndex
        {
            public function __construct() {}

            public function name(): string
            {
                return 'orders';
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->keyword('status')->description('Order status.');
                $props->nested('orders', function (NewProperties $props): void {
                    $props->keyword('id')->description('Line id.');
                })->description('Order lines.');

                return $props;
            }
        };

        $this->assertStringContainsString(
            "- orders [nested]: orders:{id:'value'} — Order lines.",
            (new SigmieIndexTool($index))->description(),
        );
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_nested_field_is_filterable(): void
    {
        $index = new class extends SigmieIndex
        {
            public function __construct() {}

            public function name(): string
            {
                return 'orders';
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->nested('orders', function (NewProperties $props): void {
                    $props->keyword('id');
                    $props->object('customer', function (NewProperties $props): void {
                        $props->keyword('city');
                    });
                });

                return $props;
            }
        };

        $orders = null;

        foreach ((new SigmieIndexSchemaTool($index))->result(new Request([]))['fields'] as $field) {
            if ($field['name'] === 'orders') {
                $orders = $field;
            }
        }

        $this->assertTrue($orders['filterable']);
        $this->assertFalse($orders['sortable']);
        $this->assertFalse($orders['facetable']);
        $this->assertSame("orders:{id:'value' AND customer.city:'value'}", $orders['filter']);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_filter_value_limit_of_zero_returns_nothing(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');

        $index = $this->capturingIndex($props);
        self::$facetRaw = ['sentinel' => true];

        $zero = (new SigmieFilterValuesTool($index))->result(new Request(['field' => 'brand', 'limit' => 0]));
        $stringZero = (new SigmieFilterValuesTool($index))->result(new Request(['field' => 'brand', 'limit' => '0']));

        $this->assertSame([], $zero['values']);
        $this->assertSame([], $stringZero['values']);
        $this->assertSame(['sentinel' => true], self::$facetRaw);

        (new SigmieFilterValuesTool($index))->result(new Request(['field' => 'brand', 'limit' => null]));

        $this->assertSame(20, $this->termsSize(json_encode(self::$facetRaw), 'brand'));
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_sort_named_zero_is_sent(): void
    {
        $props = new NewProperties;
        $props->keyword('0');

        $index = $this->capturingIndex($props);
        $tool = new SigmieIndexTool($index);

        $tool->result(new Request(['query' => '', 'sort' => '0']));
        $this->assertSame('0', self::$sentSort);

        $tool->result(new Request(['query' => '', 'facets' => '0']));
        $this->assertSame('0', self::$sentFacets);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_numeric_sort_reaches_the_search(): void
    {
        $props = new NewProperties;
        $props->keyword('0');
        $props->keyword('1');

        $index = $this->capturingIndex($props);
        $tool = new SigmieIndexTool($index);

        $tool->result(new Request(['query' => '', 'sort' => 1]));
        $this->assertSame('1', self::$sentSort);

        $tool->result(new Request(['query' => '', 'facets' => false]));
        $this->assertSame('', self::$sentFacets);

        $handled = $tool->handle(new Request(['query' => '', 'sort' => 0]));
        $this->assertStringNotContainsString('expressionFields()', $handled);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_geo_sort_after_a_colon_is_refused(): void
    {
        $props = new NewProperties;
        $props->keyword('x');
        $props->geoPoint('location');
        $props->nested('contact', function (NewProperties $nested): void {
            $nested->geoPoint('location');
        });

        $tool = new SigmieIndexTool($this->capturingIndex($props, except: ['location', 'contact.location']));

        foreach ([
            'location[40.71,-74.00]:km:asc' => 'location',
            'x:location[40.71,-74.00]:km:asc' => 'location',
            'x:contact.location[52.49,13.77]:m:asc' => 'contact.location',
        ] as $sort => $field) {
            try {
                $tool->result(new Request(['query' => '', 'sort' => $sort]));
                $this->fail($sort);
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString(
                    'Field '.$field.' is private and cannot be listed, grouped, faceted or sorted',
                    $exception->getMessage(),
                );
            }
        }
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_filter_value_named_zero_stays_an_object(): void
    {
        $connection = new class extends ElasticsearchConnection
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

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                throw new RuntimeException('no cluster');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('no cluster');
            }
        };

        $props = new NewProperties;
        $props->keyword('brand');

        $index = new class($connection, $props) extends SigmieIndex
        {
            public function __construct(
                private ElasticsearchConnection $connection,
                private NewProperties $props,
            ) {}

            public function name(): string
            {
                return 'products';
            }

            public function properties(): NewProperties
            {
                return $this->props;
            }

            public function newSearch(): NewSearch
            {
                return (new class($this->connection) extends NewSearch
                {
                    public function __construct(ElasticsearchConnection $connection)
                    {
                        parent::__construct($connection);
                    }

                    public function get(): ResponseFormater
                    {
                        $formatter = new SigmieSearchResponse($this->properties);
                        $formatter->context($this->searchContext)
                            ->errors([])
                            ->queryResponseRaw(['hits' => ['total' => ['value' => 0], 'hits' => []]])
                            ->facetsResponseRaw(['aggregations' => [
                                'brand' => [
                                    'doc_count' => 4,
                                    'brand' => [
                                        'sum_other_doc_count' => 0,
                                        'buckets' => [
                                            ['key' => '0', 'doc_count' => 4],
                                        ],
                                    ],
                                ],
                            ]]);

                        return $formatter;
                    }
                })->properties($this->props);
            }
        };

        $json = (new SigmieFilterValuesTool($index))->handle(new Request([
            'field' => 'brand',
            'limit' => 5,
        ]));

        $this->assertStringContainsString('"values":{"0":4}', $json);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_keyword_equality_note_names_the_lowercase_normalizer(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');
        $props->text('title')->keyword();
        $props->caseSensitiveKeyword('code');

        $analysis = new Analysis;
        $mapped = $props->get($analysis);
        $index = $this->schemaIndex($props);
        $notes = (new SigmieIndexSchemaTool($index))->result(new Request([]))['notes'][0];
        $description = (new SigmieIndexTool($index))->description();

        $this->assertSame(['lowercase'], $analysis->toRaw()['normalizer']['brand_field_normalizer']['filter']);
        $this->assertArrayNotHasKey('normalizer', $mapped->get('title')->toRaw()['title']['fields']['keyword']);
        $this->assertArrayNotHasKey('normalizer', $mapped->get('code')->toRaw()['code']);

        $filters = new FilterParser($props);

        foreach (["brand:'Apple'", "title:'Apple'", "code:'Apple'"] as $expression) {
            $term = $filters->parse($expression)->toRaw()['bool']['must'][0]['term'];
            $clause = $term[array_key_first($term)];

            $this->assertSame('Apple', $clause['value']);
        }

        $note = sprintf("Equality on keyword() is case-insensitive because its normalizer lowercases. Equality on a text field's keyword subfield and on caseSensitiveKeyword() is case-sensitive. If a filter returns 0 unexpectedly, call discover_filter_values_%s on that field to confirm the exact stored values (including casing).", $index->name());

        $this->assertSame($note, $notes);
        $matching = "Matching: equality on keyword() is case-insensitive because its normalizer lowercases. Equality on a text field's keyword subfield and on caseSensitiveKeyword() is case-sensitive. If a filter returns 0 unexpectedly, call discover_filter_values to confirm the exact stored value.";

        $this->assertStringContainsString(str_replace('discover_filter_values', 'discover_filter_values_'.$index->name(), $matching), $description);
        $this->assertStringContainsString(str_replace('discover_filter_values', 'discover_filter_values_products', $matching), (string) file_get_contents(dirname(__DIR__).'/docs/laravel-ai.md'));
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_facet_syntax_reserves_interval_for_price(): void
    {
        $props = new NewProperties;
        $props->number('rating');
        $props->price('price');
        $props->date('created_at');
        $props->keyword('brand');

        $raw = (new FacetParser($props))->parse('rating:50 price:50 created_at:50 brand:20')->toRaw();

        $this->assertSame(['stats' => ['field' => 'rating']], $raw['rating']['aggs']['rating']);
        $this->assertSame(
            ['stats' => ['field' => 'created_at', 'format' => 'strict_date_optional_time_nanos']],
            $raw['created_at']['aggs']['created_at'],
        );
        $this->assertSame(50, $raw['price']['aggs']['price_histogram']['histogram']['interval']);
        $this->assertSame(20, $raw['brand']['aggs']['brand']['terms']['size']);

        $index = $this->schemaIndex($props);
        $facets = 'field1 field2:20 (space-separated; :size for term fields, :interval for price; number, date, and datetime take no parameter)';
        $syntax = (new SigmieIndexSchemaTool($index))->result(new Request([]))['filter_syntax']['facets'];
        $description = (new SigmieIndexTool($index))->description();
        $docs = (string) file_get_contents(dirname(__DIR__).'/docs/laravel-ai.md');

        $this->assertSame($facets, $syntax);
        $this->assertStringContainsString('Facets: '.$facets, $description);
        $this->assertStringContainsString('Facets: '.$facets, $docs);
    }

    private function schemaIndex(NewProperties $props): SigmieIndex
    {
        return new class($props) extends SigmieIndex
        {
            public function __construct(private NewProperties $props) {}

            public function name(): string
            {
                return 'products';
            }

            public function properties(): NewProperties
            {
                return $this->props;
            }
        };
    }

    private function histogramInterval(array $raw): ?int
    {
        if (preg_match('/"interval"\s*:\s*(\d+)/', (string) json_encode($raw), $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function termsSize(string $raw, string $field): ?int
    {
        $decoded = json_decode($raw, true);
        $size = $decoded[$field]['aggs'][$field]['terms']['size'] ?? null;

        return is_int($size) ? $size : null;
    }

    private function capturingIndex(NewProperties $props, array $hits = ['total' => ['value' => 0], 'hits' => []], array $except = [], array $rawAggregations = [], ?ResponseFormater $formatter = null): SigmieIndex
    {
        $connection = new class extends ElasticsearchConnection
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

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                throw new RuntimeException('no cluster');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('no cluster');
            }
        };

        return new class($connection, $props, $hits, $except, $rawAggregations, $formatter) extends SigmieIndex
        {
            public function __construct(
                private ElasticsearchConnection $connection,
                private NewProperties $props,
                private array $hits,
                private array $except,
                private array $rawAggregations,
                private ?ResponseFormater $responseFormatter,
            ) {}

            public function name(): string
            {
                return 'products';
            }

            public function properties(): NewProperties
            {
                return $this->props;
            }

            public function exceptFromTools(): array
            {
                return $this->except;
            }

            public function newSearch(): NewSearch
            {
                $hits = $this->hits;

                $search = new class($this->connection, $hits, $this->rawAggregations) extends NewSearch
                {
                    public function __construct(
                        ElasticsearchConnection $connection,
                        private array $hits,
                        private array $rawAggregations,
                    ) {
                        parent::__construct($connection);
                    }

                    public function get(): ResponseFormater
                    {
                        SigmieIndexToolTest::$sentSort = $this->searchContext->sortString;
                        SigmieIndexToolTest::$sentFacets = $this->searchContext->facetString;
                        SigmieIndexToolTest::$facetRaw = $this->facets->toRaw();

                        $formatter = $this->formatter ?? new SigmieSearchResponse($this->properties);
                        $formatter->context($this->searchContext)
                            ->errors([])
                            ->queryResponseRaw(['hits' => $this->hits])
                            ->facetsResponseRaw(['aggregations' => $this->rawAggregations]);

                        return $formatter;
                    }
                };

                if ($this->responseFormatter instanceof ResponseFormater) {
                    $search->formatter($this->responseFormatter);
                }

                return $search->properties($this->props);
            }
        };
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_cluster_error_without_a_reason_returns_search_guidance(): void
    {
        $index = $this->createMock(SigmieIndex::class);
        $index->method('newSearch')->willThrowException(new ElasticsearchException(['error' => ['type' => 'search_phase_execution_exception']], 500));

        $json = (new SigmieIndexTool($index))->handle(new Request(['query' => 'books']));

        $this->assertSame([
            'error' => "Elasticsearch could not complete the search. Check the field names and the filter/sort syntax in this tool's description, then try again.",
        ], json_decode($json, true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function schema_omits_vectors_and_excludes_them_from_nested_filters(): void
    {
        $props = new NewProperties;
        $props->vector('embedding');
        $props->keyword('title');
        $props->nested('variants', function (NewProperties $children): void {
            $children->vector('embedding');
            $children->keyword('sku');
        });
        $tool = new SigmieIndexSchemaTool($this->schemaIndex($props));

        $fields = array_column($tool->result(new Request([]))['fields'], null, 'name');

        $this->assertSame(['title', 'variants'], array_keys($fields));
        $this->assertSame("variants:{sku:'value'}", $fields['variants']['filter']);
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function names_for_long_indices_stay_distinct_and_fit_the_tool_limit(): void
    {
        $first = $this->createMock(SigmieIndex::class);
        $second = $this->createMock(SigmieIndex::class);
        $first->method('name')->willReturn(str_repeat('a', 64).'one');
        $second->method('name')->willReturn(str_repeat('a', 64).'two');

        $tool = new SigmieIndexTool($first);
        $name = $tool->name();

        $this->assertMatchesRegularExpression('/^search_index_[a-zA-Z0-9_-]+$/D', $name);
        $this->assertLessThanOrEqual(64, strlen($name));
        $this->assertSame($name, $tool->name());
        $this->assertNotSame($name, (new SigmieIndexTool($second))->name());
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_structured_sort_argument_is_treated_as_absent(): void
    {
        $props = new NewProperties;
        $props->keyword('brand');

        $tool = new SigmieIndexTool($this->capturingIndex($props));

        $tool->result(new Request(['sort' => 'brand:desc']));
        $this->assertSame('brand:desc', self::$sentSort);

        $result = $tool->result(new Request(['sort' => ['brand' => 'asc']]));

        $this->assertSame(['total' => 0, 'hits' => []], $result);
        $this->assertSame('', self::$sentSort);
    }

    public function test_a_restored_field_without_doc_values_is_not_described_as_sortable(): void
    {
        $this->indexAPICall('products', 'PUT', ['mappings' => ['properties' => [
            'brand' => ['type' => 'keyword'],
            'sku' => ['type' => 'keyword', 'doc_values' => false],
            'stock' => ['type' => 'integer', 'doc_values' => false],
        ]]]);

        $props = new NewProperties;
        foreach ($this->sigmie->index('products')->mappings->properties()->toArray() as $field) {
            $props->type($field);
        }

        $fields = (new SigmieIndexSchemaTool($this->schemaIndex($props)))->result(new Request)['fields'];

        $this->assertSame(['brand' => true, 'sku' => false, 'stock' => false], array_column($fields, 'sortable', 'name'));
    }

    public function test_a_restored_field_without_index_or_doc_values_is_not_described_as_filterable(): void
    {
        $storedOnly = ['index' => false, 'doc_values' => false];
        $this->indexAPICall('products', 'PUT', ['mappings' => ['properties' => [
            'brand' => ['type' => 'keyword'],
            'sku' => ['type' => 'keyword', ...$storedOnly],
            'stock' => ['type' => 'integer', ...$storedOnly],
            'active' => ['type' => 'boolean', ...$storedOnly],
        ]]]);

        $props = new NewProperties;
        foreach ($this->sigmie->index('products')->mappings->properties()->toArray() as $field) {
            $props->type($field);
        }

        $fields = (new SigmieIndexSchemaTool($this->schemaIndex($props)))->result(new Request)['fields'];

        $this->assertSame(['active' => false, 'brand' => true, 'sku' => false, 'stock' => false], array_column($fields, 'filterable', 'name'));
    }

    public function test_a_restored_date_filter_example_uses_the_field_format(): void
    {
        $this->indexAPICall('products', 'PUT', ['mappings' => ['properties' => [
            'created' => ['type' => 'date'],
            'shipped' => ['type' => 'date', 'format' => 'dd/MM/yyyy'],
            'paid' => ['type' => 'date', 'format' => 'epoch_second'],
        ]]]);

        $props = new NewProperties;
        foreach ($this->sigmie->index('products')->mappings->properties()->toArray() as $field) {
            $props->type($field);
        }

        $fields = (new SigmieIndexSchemaTool($this->schemaIndex($props)))->result(new Request)['fields'];

        $this->assertSame([
            'created' => "created>'2024-01-01' created<'2024-12-31'",
            'paid' => "paid>'1704067200' paid<'1735603200'",
            'shipped' => "shipped>'01/01/2024' shipped<'31/12/2024'",
        ], array_column($fields, 'filter', 'name'));
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function sequential_filter_value_keys_remain_json_object_members_with_a_custom_formatter(): void
    {
        $props = new NewProperties;
        $props->keyword('code');

        $formatter = new class($props->get()) extends SigmieSearchResponse
        {
            public function facet(string $field): array
            {
                return $field === 'code' ? [4, 3] : [];
            }
        };

        $tool = new SigmieFilterValuesTool($this->capturingIndex($props, formatter: $formatter));
        $request = new Request(['field' => 'code']);
        $this->assertSame([4, 3], $tool->result($request)['values']);

        $json = $tool->handle($request);
        $values = json_decode($json, flags: JSON_THROW_ON_ERROR)->values ?? null;

        $this->assertInstanceOf(stdClass::class, $values, $json);
        $this->assertSame(4, $values->{'0'});
        $this->assertSame(3, $values->{'1'});
    }
}
