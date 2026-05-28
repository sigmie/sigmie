<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Base\ElasticsearchException;
use Sigmie\Mappings\Types\Keyword;

require_once __DIR__.'/Stubs/LaravelAiStubs.php';

use Laravel\Ai\Tools\Request;
use Sigmie\AI\AsTool;
use Sigmie\AI\SigmieFilterValuesTool;
use Sigmie\AI\SigmieIndexTool;
use Sigmie\AI\SigmieSampleDocumentsTool;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;
use Sigmie\SigmieIndex;
use Sigmie\Testing\TestCase;

class SigmieIndexToolTest extends TestCase
{
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
    public function tools_returns_search_values_and_sample_tools(): void
    {
        $index = $this->createProductIndex();

        [$search, $values, $sample] = $index->tools();

        $this->assertInstanceOf(SigmieIndexTool::class, $search);
        $this->assertInstanceOf(SigmieFilterValuesTool::class, $values);
        $this->assertInstanceOf(SigmieSampleDocumentsTool::class, $sample);
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
    public function filter_values_errors_on_unknown_field(): void
    {
        $index = $this->createProductIndex();

        // No client-side validation: an unknown/non-facetable field surfaces as an engine
        // error, which the agent SDK / tool-call dispatch reports back to the model.
        $this->expectException(ElasticsearchException::class);

        (new SigmieFilterValuesTool($index))->handle(new Request(['field' => 'nope']));
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
    public function tools_expose_array_results(): void
    {
        $index = $this->createProductIndex();

        foreach ($index->tools() as $tool) {
            $this->assertInstanceOf(\Sigmie\AI\Contracts\ArrayResult::class, $tool);
        }
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
}
