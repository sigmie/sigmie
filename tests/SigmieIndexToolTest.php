<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Mappings\Types\Keyword;
use Sigmie\Parse\ParseException;
use Sigmie\Tests\Stubs\FakeJsonSchema;

require_once __DIR__.'/Stubs/LaravelAiStubs.php';

use Laravel\Ai\Tools\Request;
use Sigmie\AI\AsTool;
use Sigmie\AI\SigmieFilterValuesTool;
use Sigmie\AI\SigmieGetDocumentsTool;
use Sigmie\AI\SigmieIndexSchemaTool;
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

        $this->assertSame('describe_index', $schema->name());
        $this->assertStringContainsString($index->name(), $schema->description());
        $this->assertSame([], $schema->schema(new FakeJsonSchema));
        $this->assertSame('discover_filter_values', $filterValues->name());
        $this->assertStringContainsString($index->name(), $filterValues->description());
        $this->assertSame('get_documents', $getDocuments->name());
        $this->assertStringContainsString($index->name(), $getDocuments->description());
        $this->assertSame('sample_documents', $sampleDocuments->name());
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
}
